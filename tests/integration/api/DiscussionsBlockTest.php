<?php

namespace ErnestDefoe\Herald\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Sending\Processor;
use ErnestDefoe\Herald\Tests\integration\SeedsHerald;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\Test;

/**
 * The Latest discussions block lists the forum as a GUEST sees it, and the
 * list is frozen when the send begins.
 */
class DiscussionsBlockTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsHerald;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-herald');
        $this->seedHerald();

        // Something only signed-in members may see, the way a staff-only tag
        // hides its discussions from guests.
        $this->extend((new Extend\ModelVisibility(Discussion::class))->scope(function (User $actor, Builder $query) {
            if ($actor->isGuest()) {
                $query->where('discussions.title', 'not like', 'Staff%');
            }
        }));

        $this->prepareDatabase([
            Discussion::class => [
                $this->discussion(1, 'Public news', 1),
                $this->discussion(2, 'Staff plans', 2),
                $this->discussion(3, 'Hidden one', 3, ['hidden_at' => Carbon::now()]),
                $this->discussion(4, 'Private chat', 4, ['is_private' => true]),
                $this->discussion(5, 'Older topic', 10),
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>The <b>first</b> post</p></t>', 'created_at' => Carbon::now()],
                ['id' => 5, 'discussion_id' => 5, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Old words</p></t>', 'created_at' => Carbon::now()->subDays(10)],
            ],
        ]);
    }

    private function discussion(int $id, string $title, int $daysAgo, array $extra = []): array
    {
        return $extra + [
            // Only discussions 1 and 5 have a first post seeded; the others
            // must not point at one, or MySQL and PostgreSQL refuse the row.
            'id' => $id, 'title' => $title, 'slug' => 'd'.$id, 'user_id' => 2, 'first_post_id' => in_array($id, [1, 5], true) ? $id : null, 'comment_count' => 3,
            'created_at' => Carbon::now()->subDays($daysAgo), 'last_posted_at' => Carbon::now()->subDays($daysAgo), 'is_private' => false,
        ];
    }

    private function block(string $id, array $data = []): array
    {
        return ['id' => $id, 'type' => 'discussions', 'data' => $data + ['sort' => 'newest', 'limit' => 5, 'layout' => 'excerpt']];
    }

    private function mailing(array $blocks): int
    {
        return $this->body($this->call('POST', '/api/herald/mailings', 1, ['subject' => 'Digest', 'type' => 'builder', 'blocks' => $blocks]))['data']['id'];
    }

    private function preview(int $id): string
    {
        return $this->body($this->call('POST', '/api/herald/preview', 1, ['id' => $id]))['html'];
    }

    #[Test]
    public function it_lists_only_what_a_guest_can_see()
    {
        // The admin can see the staff discussion; the email still must not.
        $this->assertSame(200, $this->call('GET', '/api/discussions/2', 1)->getStatusCode());

        $html = $this->preview($this->mailing([$this->block('a')]));

        $this->assertStringContainsString('Public news', $html);
        $this->assertStringContainsString('Older topic', $html);
        $this->assertStringContainsString('The first post', $html, 'The excerpt, without its formatting');
        $this->assertStringNotContainsString('Staff plans', $html);
        $this->assertStringNotContainsString('Hidden one', $html);
        $this->assertStringNotContainsString('Private chat', $html);
    }

    #[Test]
    public function a_block_can_skip_what_an_earlier_one_listed()
    {
        $html = $this->preview($this->mailing([
            $this->block('a', ['limit' => 1]),
            $this->block('b', ['skipEarlier' => true, 'heading' => 'More']),
        ]));

        $this->assertSame(1, substr_count($html, 'Public news'));
        $this->assertSame(1, substr_count($html, 'Older topic'));
    }

    #[Test]
    public function the_list_is_frozen_when_the_send_begins()
    {
        $id = $this->mailing([$this->block('a')]);
        $this->assertSame(200, $this->call('POST', "/api/herald/mailings/$id/send", 1, [])->getStatusCode());

        $this->database()->table('discussions')->insert($this->discussion(6, 'Brand new', 0));

        $html = $this->preview($id);
        $this->assertStringContainsString('Public news', $html);
        $this->assertStringNotContainsString('Brand new', $html, 'Everyone gets the list as it was when the send began');
        $this->assertNotNull($this->database()->table('herald_mailings')->where('id', $id)->value('frozen'));
    }

    #[Test]
    public function an_issue_with_nothing_to_list_is_skipped_not_sent()
    {
        $id = $this->mailing([['id' => 'hero', 'type' => 'hero', 'data' => ['title' => 'This week']], $this->block('a', ['sort' => 'trending'])]);

        // Nothing has had a reply in the last week: the trending list is empty.
        $this->database()->table('discussions')->update(['last_posted_at' => Carbon::now()->subDays(30)]);

        $this->assertSame(422, $this->call('POST', "/api/herald/mailings/$id/send", 1, [])->getStatusCode(), 'Sent by hand: an error');

        $mailing = Mailing::query()->find($id);
        $this->assertFalse($this->app()->getContainer()->make(Processor::class)->start($mailing, null));
        $this->assertSame('skipped', $mailing->refresh()->status);
        $this->assertSame(0, $this->database()->table('herald_deliveries')->where('mailing_id', $id)->count());
    }

    #[Test]
    public function the_quick_tag_lists_discussions_in_an_editor_mailing()
    {
        $id = $this->body($this->call('POST', '/api/herald/mailings', 1, ['subject' => 'News', 'content' => "This week:\n\n{latest_discussions}"]))['data']['id'];

        $html = $this->preview($id);

        $this->assertStringContainsString('Public news', $html);
        $this->assertStringNotContainsString('Staff plans', $html);
        $this->assertStringNotContainsString('{latest_discussions}', $html);
    }
}
