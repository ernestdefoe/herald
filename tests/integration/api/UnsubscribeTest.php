<?php

namespace ErnestDefoe\Herald\Tests\integration\api;

use ErnestDefoe\Herald\Tests\integration\SeedsHerald;
use ErnestDefoe\Herald\Unsubscribe\Token;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * A member's own switch: the one-click link in every mailing, and the field
 * on their settings page. Nobody else can turn it back on.
 */
class UnsubscribeTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsHerald;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-herald');
        $this->seedHerald();
    }

    private function token(int $userId): string
    {
        return $this->app()->getContainer()->make(Token::class)->make(User::query()->find($userId));
    }

    private function subscribed(int $userId): bool
    {
        return (bool) $this->database()->table('users')->where('id', $userId)->value('herald_subscribed');
    }

    #[Test]
    public function the_link_unsubscribes_its_member_in_one_click()
    {
        $token = $this->token(2);

        $page = $this->send($this->request('GET', "/herald/unsubscribe/2/$token"));
        $this->assertSame(200, $page->getStatusCode());
        $this->assertTrue($this->subscribed(2), 'Opening the page changes nothing');

        // RFC 8058: the mail client posts with no session and no CSRF token.
        $post = $this->request('POST', "/herald/unsubscribe/2/$token")
            ->withBody((new StreamFactory())->createStream('List-Unsubscribe=One-Click'))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->assertSame(200, $this->send($post)->getStatusCode());

        $this->assertFalse($this->subscribed(2));
    }

    #[Test]
    public function a_token_for_someone_else_does_nothing()
    {
        $token = $this->token(3);

        $this->assertSame(404, $this->send($this->request('POST', "/herald/unsubscribe/2/$token"))->getStatusCode());
        $this->assertTrue($this->subscribed(2));
    }

    #[Test]
    public function a_member_sees_and_changes_only_their_own_switch()
    {
        $own = $this->send($this->request('GET', '/api/users/2', ['authenticatedAs' => 2]));
        $this->assertTrue(json_decode((string) $own->getBody(), true)['data']['attributes']['heraldSubscribed']);

        $other = $this->send($this->request('GET', '/api/users/2', ['authenticatedAs' => 1]));
        $this->assertArrayNotHasKey('heraldSubscribed', json_decode((string) $other->getBody(), true)['data']['attributes']);

        $this->send($this->request('PATCH', '/api/users/4', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['type' => 'users', 'id' => '4', 'attributes' => ['heraldSubscribed' => true]]],
        ]));
        $this->assertFalse($this->subscribed(4), 'An admin cannot opt somebody back in');

        $this->send($this->request('PATCH', '/api/users/2', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'users', 'id' => '2', 'attributes' => ['heraldSubscribed' => false]]],
        ]));
        $this->assertFalse($this->subscribed(2));
    }
}
