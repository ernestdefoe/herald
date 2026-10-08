<?php

namespace ErnestDefoe\Herald\Tests\integration\api;

use ErnestDefoe\Herald\Tests\integration\SeedsHerald;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MailingsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsHerald;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-herald');
        $this->seedHerald();
    }

    private function mailing(): object
    {
        return $this->database()->table('herald_mailings')->where('id', 1)->first();
    }

    public static function staffRoutes(): array
    {
        return [
            'meta' => ['GET', '/api/herald/meta'],
            'count' => ['POST', '/api/herald/count'],
            'preview' => ['POST', '/api/herald/preview'],
            'list' => ['GET', '/api/herald/mailings'],
            'create' => ['POST', '/api/herald/mailings'],
            'show' => ['GET', '/api/herald/mailings/1'],
            'update' => ['PATCH', '/api/herald/mailings/1'],
            'delete' => ['DELETE', '/api/herald/mailings/1'],
            'copy' => ['POST', '/api/herald/mailings/1/copy'],
            'test' => ['POST', '/api/herald/mailings/1/test'],
            'send' => ['POST', '/api/herald/mailings/1/send'],
            'process' => ['POST', '/api/herald/mailings/1/process'],
            'cancel' => ['POST', '/api/herald/mailings/1/cancel'],
        ];
    }

    #[Test]
    #[DataProvider('staffRoutes')]
    public function a_member_cannot_mail_the_forum(string $method, string $path)
    {
        $response = $this->call($method, $path, 2, $method === 'GET' ? null : ['subject' => 'Hijacked']);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('draft', $this->mailing()->status);
        $this->assertSame(1, $this->database()->table('herald_mailings')->count());
    }

    #[Test]
    public function a_guest_cannot_mail_the_forum()
    {
        $this->assertSame(401, $this->call('POST', '/api/herald/mailings/1/send', null, [])->getStatusCode());
        $this->assertSame('draft', $this->mailing()->status);
    }

    #[Test]
    public function the_forum_says_who_may_send()
    {
        $this->assertFalse($this->body($this->call('GET', '/api', 2))['data']['attributes']['canSendHeraldMail']);
        $this->assertTrue($this->body($this->call('GET', '/api', 1))['data']['attributes']['canSendHeraldMail']);
    }

    #[Test]
    public function the_count_leaves_out_the_opted_out_and_unconfirmed()
    {
        $body = $this->body($this->call('POST', '/api/herald/count', 1, ['filters' => []]));

        $this->assertSame(['reach' => 3, 'optedOut' => 1, 'unconfirmed' => 1], $body);
    }

    #[Test]
    public function a_group_filter_narrows_the_audience()
    {
        $body = $this->body($this->call('POST', '/api/herald/count', 1, ['filters' => ['groups' => ['include' => [Group::MODERATOR_ID]]]]));

        $this->assertSame(1, $body['reach']);
    }

    #[Test]
    public function a_mailing_reaches_every_subscribed_confirmed_member_once()
    {
        $this->assertSame(200, $this->call('POST', '/api/herald/mailings/1/send', 1, [])->getStatusCode());
        $this->assertSame(3, (int) $this->mailing()->recipient_total);

        $response = $this->call('POST', '/api/herald/mailings/1/process', 1, []);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        // The next batch finds nobody left and closes the mailing.
        $this->call('POST', '/api/herald/mailings/1/process', 1, []);

        $mailing = $this->mailing();
        $this->assertSame('sent', $mailing->status);
        $this->assertSame(3, (int) $mailing->sent_count);
        $this->assertSame(0, (int) $mailing->failed_count);
    }

    #[Test]
    public function a_mailing_needs_a_subject_before_it_is_sent()
    {
        $this->database()->table('herald_mailings')->where('id', 1)->update(['subject' => ' ']);

        $this->assertSame(422, $this->call('POST', '/api/herald/mailings/1/send', 1, [])->getStatusCode());
        $this->assertSame('draft', $this->mailing()->status);
    }

    #[Test]
    public function a_mailing_being_sent_can_be_cancelled_but_not_deleted()
    {
        $this->call('POST', '/api/herald/mailings/1/send', 1, []);

        $this->assertSame(422, $this->call('DELETE', '/api/herald/mailings/1', 1)->getStatusCode());

        $this->call('POST', '/api/herald/mailings/1/cancel', 1, []);
        $this->assertSame('cancelled', $this->mailing()->status);

        $this->call('POST', '/api/herald/mailings/1/process', 1, []);
        $this->assertSame(0, (int) $this->mailing()->sent_count, 'Nothing goes out after a cancel');
    }

    #[Test]
    public function an_admin_drafts_a_mailing()
    {
        $response = $this->call('POST', '/api/herald/mailings', 1, ['subject' => 'Season opener', 'content' => 'Kickoff is Saturday', 'filters' => []]);

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $data = $this->body($response)['data'];
        $this->assertSame('Season opener', $data['subject']);
        $this->assertSame('draft', $data['status']);
    }
}
