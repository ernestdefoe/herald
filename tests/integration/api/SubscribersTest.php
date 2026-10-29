<?php

namespace ErnestDefoe\Herald\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Herald\Subscriber;
use ErnestDefoe\Herald\Tests\integration\SeedsHerald;
use ErnestDefoe\Herald\Unsubscribe\Token;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * The signup widget's guest list: double opt-in, one answer for every
 * address, rate limits, and its own one-click unsubscribe.
 */
class SubscribersTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsHerald;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-herald');
        $this->seedHerald();
    }

    private function subscribe(string $email, array $extra = []): object
    {
        // The rate limits live in the cache, which outlives a test.
        static $flushed = false;
        if (! $flushed) {
            $this->app()->getContainer()->make(Repository::class)->clear();
            $flushed = true;
        }

        return $this->call('POST', '/api/herald/subscribe', null, ['email' => $email] + $extra);
    }

    private function row(string $email): ?object
    {
        return $this->database()->table('herald_subscribers')->where('email', $email)->first();
    }

    /** A pending subscriber whose confirmation link we know. */
    private function pending(string $email): array
    {
        $this->app();
        $subscriber = new Subscriber();
        $subscriber->email = $email;
        $token = $subscriber->issueToken();
        $subscriber->save();

        return [$subscriber, $token];
    }

    #[Test]
    public function a_guest_signs_up_and_must_confirm_first()
    {
        $response = $this->subscribe('New.Reader@Example.com');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $row = $this->row('new.reader@example.com');
        $this->assertNotNull($row, 'Stored lower-cased');
        $this->assertNull($row->confirmed_at);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $row->token_hash, 'Only a hash of the token is kept');

        $count = $this->body($this->call('POST', '/api/herald/count', 1, ['filters' => ['guests' => true]]));
        $this->assertSame(0, $count['guests'], 'Unconfirmed addresses are never mailed');
    }

    #[Test]
    public function every_address_gets_the_same_answer()
    {
        [$known] = $this->pending('known@example.com');
        $known->confirmed_at = Carbon::now();
        $known->save();

        $new = $this->subscribe('fresh@example.com');
        $existing = $this->subscribe('known@example.com');
        $member = $this->subscribe('normal@machine.local');

        $this->assertSame((string) $new->getBody(), (string) $existing->getBody());
        $this->assertSame((string) $new->getBody(), (string) $member->getBody());
        $this->assertSame(200, $member->getStatusCode());
        $this->assertNull($this->row('normal@machine.local'), "A member's address never becomes a guest subscriber");
    }

    #[Test]
    public function a_bot_filling_the_hidden_field_is_thanked_and_ignored()
    {
        $this->assertSame(200, $this->subscribe('bot@example.com', ['website' => 'http://spam'])->getStatusCode());
        $this->assertNull($this->row('bot@example.com'));
    }

    #[Test]
    public function signups_are_rate_limited_per_ip()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(200, $this->subscribe("reader$i@example.com")->getStatusCode());
        }

        $this->assertSame(429, $this->subscribe('reader6@example.com')->getStatusCode());
        $this->assertNull($this->row('reader6@example.com'));
    }

    #[Test]
    public function members_use_their_own_switch_not_the_guest_form()
    {
        $this->assertSame(403, $this->call('POST', '/api/herald/subscribe', 2, ['email' => 'x@example.com'])->getStatusCode());
    }

    #[Test]
    public function opening_the_link_asks_and_only_the_button_confirms()
    {
        [$subscriber, $token] = $this->pending('reader@example.com');

        $this->assertSame(200, $this->send($this->request('GET', "/herald/confirm/{$subscriber->id}/$token"))->getStatusCode());
        $this->assertNull($this->row('reader@example.com')->confirmed_at, 'A mail scanner opening the link confirms nothing');

        $this->assertSame(404, $this->send($this->request('POST', "/herald/confirm/{$subscriber->id}/".str_repeat('0', 48)))->getStatusCode());

        $this->assertSame(200, $this->send($this->request('POST', "/herald/confirm/{$subscriber->id}/$token"))->getStatusCode());
        $row = $this->row('reader@example.com');
        $this->assertNotNull($row->confirmed_at);
        $this->assertNull($row->token_hash, 'The link works once');

        $count = $this->body($this->call('POST', '/api/herald/count', 1, ['filters' => ['guests' => true]]));
        $this->assertSame(1, $count['guests']);
        $this->assertSame(4, $count['reach']);
    }

    #[Test]
    public function a_confirmation_link_expires()
    {
        [$subscriber, $token] = $this->pending('late@example.com');

        Carbon::setTestNow(Carbon::now()->addHours(Subscriber::CONFIRM_HOURS + 1));

        try {
            $this->assertSame(404, $this->send($this->request('POST', "/herald/confirm/{$subscriber->id}/$token"))->getStatusCode());
            $this->assertNull($this->row('late@example.com')->confirmed_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function a_guest_unsubscribes_in_one_click_with_their_own_token()
    {
        [$subscriber] = $this->pending('leaving@example.com');
        $subscriber->confirmed_at = Carbon::now();
        $subscriber->save();

        $tokens = $this->app()->getContainer()->make(Token::class);
        $memberToken = $tokens->make(User::query()->find(2));

        // A member's token for the same number is not a subscriber's.
        $this->assertSame(404, $this->send($this->request('POST', "/herald/unsubscribe/s/{$subscriber->id}/$memberToken"))->getStatusCode());

        $token = $tokens->makeForSubscriber($subscriber);
        $post = $this->request('POST', "/herald/unsubscribe/s/{$subscriber->id}/$token")
            ->withBody((new StreamFactory())->createStream('List-Unsubscribe=One-Click'))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame(200, $this->send($post)->getStatusCode());
        $this->assertNotNull($this->row('leaving@example.com')->unsubscribed_at);
    }

    #[Test]
    public function staff_can_list_export_and_remove_guest_subscribers()
    {
        [$subscriber] = $this->pending('listed@example.com');

        $this->assertSame(403, $this->call('GET', '/api/herald/subscribers', 2)->getStatusCode());

        $list = $this->body($this->call('GET', '/api/herald/subscribers?q=listed', 1));
        $this->assertSame(1, $list['total']);
        $this->assertSame('pending', $list['data'][0]['status']);

        $csv = (string) $this->call('GET', '/api/herald/subscribers/export', 1)->getBody();
        $this->assertStringContainsString('listed@example.com,pending', $csv);

        $this->assertSame(200, $this->call('DELETE', "/api/herald/subscribers/{$subscriber->id}", 1)->getStatusCode());
        $this->assertNull($this->row('listed@example.com'));
    }

    #[Test]
    public function a_mailing_reaches_confirmed_guests_when_asked_to()
    {
        [$guest] = $this->pending('guest@example.com');
        $guest->confirmed_at = Carbon::now();
        $guest->save();

        $this->call('PATCH', '/api/herald/mailings/1', 1, ['filters' => ['guests' => true]]);
        $this->call('POST', '/api/herald/mailings/1/send', 1, []);

        for ($i = 0; $i < 3; $i++) {
            $this->call('POST', '/api/herald/mailings/1/process', 1, []);
        }

        $mailing = $this->database()->table('herald_mailings')->find(1);
        $this->assertSame('sent', $mailing->status);
        $this->assertSame(4, (int) $mailing->sent_count);
        $this->assertSame('sent', $this->database()->table('herald_deliveries')->where('subscriber_id', $guest->id)->value('status'));
    }
}
