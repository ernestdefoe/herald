<?php

namespace ErnestDefoe\Herald\Builder;

use Carbon\Carbon;
use ErnestDefoe\Herald\Mailing;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Post\CommentPost;
use Flarum\User\Guest;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use s9e\TextFormatter\Utils;

/**
 * What a Latest discussions block, or the {latest_discussions} quick tag,
 * lists.
 *
 * 🚨 Always as a GUEST sees the forum. A mailing goes to many people, and
 * whatever the member of staff who wrote it can see — a staff-only tag, a
 * hidden discussion — must never reach them. whereVisibleTo(new Guest) runs
 * every extension's visibility rules, the tag permissions included.
 *
 * The lists are frozen into the mailing when the send begins, so everyone
 * gets the same list however long the send takes. One query per block, plus
 * one each for the authors and first posts of all its rows.
 */
class Discussions
{
    /** The quick tag's settings: the five most recently active, anywhere. */
    public const TAG = 'latest_discussions';
    private const TAG_BLOCK = ['tags' => [], 'children' => false, 'sort' => 'active', 'limit' => 5, 'layout' => 'compact', 'skipEarlier' => false];

    public function __construct(
        private ConnectionInterface $db,
        private ExtensionManager $extensions,
        private SlugManager $slugs,
        private UrlGenerator $url,
    ) {
    }

    /**
     * Whether the mailing lists discussions at all.
     */
    public function dynamic(Mailing $mailing): bool
    {
        return $mailing->isBuilt()
            ? collect($mailing->blocks ?? [])->contains('type', 'discussions')
            : str_contains((string) $mailing->content, '{'.self::TAG.'}');
    }

    /**
     * The lists for every discussions block (by block id), and for the quick
     * tag (under the key "tag"). Frozen lists are used when there are some.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function lists(Mailing $mailing): array
    {
        if (is_array($mailing->frozen)) {
            return $mailing->frozen;
        }

        if (! $this->dynamic($mailing)) {
            return [];
        }

        if (! $mailing->isBuilt()) {
            return ['tag' => $this->query(self::TAG_BLOCK, [])];
        }

        $lists = [];
        $seen = [];

        foreach ($mailing->blocks ?? [] as $block) {
            if (($block['type'] ?? null) !== 'discussions') {
                continue;
            }

            $data = $block['data'] ?? [];
            $rows = $this->query($data, ! empty($data['skipEarlier']) ? array_keys($seen) : []);

            foreach ($rows as $row) {
                $seen[$row['id']] = true;
            }

            $lists[$block['id']] = $rows;
        }

        return $lists;
    }

    /**
     * Whether a mailing that lists discussions has none to list — an issue
     * that would only say "nothing new".
     */
    public function empty(Mailing $mailing, array $lists): bool
    {
        return $this->dynamic($mailing) && collect($lists)->flatten(1)->isEmpty();
    }

    /**
     * @param int[] $skip
     * @return list<array<string, mixed>>
     */
    private function query(array $data, array $skip): array
    {
        $limit = max(1, min(10, (int) ($data['limit'] ?? 5)));
        $excerpt = ($data['layout'] ?? '') === 'excerpt';

        /** @var Builder<Discussion> $query */
        $query = Discussion::query()
            ->whereVisibleTo(new Guest())
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->when($skip, fn ($q) => $q->whereNotIn('discussions.id', $skip))
            ->with($excerpt ? ['user', 'firstPost'] : ['user'])
            ->limit($limit);

        $tags = $this->tagIds($data);

        if ($tags !== null) {
            if (! $tags) {
                return [];
            }

            $query->whereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('discussion_tag')
                ->whereColumn('discussion_tag.discussion_id', 'discussions.id')
                ->whereIn('discussion_tag.tag_id', $tags));
        }

        match ($data['sort'] ?? 'newest') {
            'active' => $query->orderByDesc('discussions.last_posted_at'),
            // Trending: the most replies in the last week, among discussions
            // with a reply in it.
            'trending' => $query
                ->where('discussions.last_posted_at', '>=', Carbon::now()->subDays(7))
                ->withCount(['comments as herald_recent' => fn ($q) => $q->where('posts.created_at', '>=', Carbon::now()->subDays(7))])
                ->orderByDesc('herald_recent')
                ->orderByDesc('discussions.last_posted_at'),
            default => $query->orderByDesc('discussions.created_at'),
        };

        $slugs = $this->slugs->forResource(Discussion::class);
        $base = $this->url->to('forum');

        return $query->get()->map(fn (Discussion $d) => [
            'id' => $d->id,
            'title' => (string) $d->title,
            'url' => $base->route('discussion', ['id' => $slugs->toSlug($d)]),
            // isset semantics: a deleted author is just no name.
            'author' => (string) ($d->user->display_name ?? ''),
            'replies' => max(0, (int) $d->comment_count - 1),
            'excerpt' => $excerpt && $d->firstPost instanceof CommentPost ? $this->excerpt((string) $d->firstPost->parsed_content) : '',
        ])->values()->all();
    }

    /**
     * The tag ids to list from: null for "every tag" (or no Tags extension),
     * an empty array when every chosen tag has gone.
     *
     * @return int[]|null
     */
    private function tagIds(array $data): ?array
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($data['tags'] ?? []))));

        if (! $ids || ! $this->extensions->isEnabled('flarum-tags')) {
            return null;
        }

        $ids = $this->db->table('tags')->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($ids && ! empty($data['children'])) {
            $ids = array_merge($ids, $this->db->table('tags')->whereIn('parent_id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        return array_values(array_unique($ids));
    }

    private function excerpt(string $xml): string
    {
        if ($xml === '') {
            return '';
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', Utils::removeFormatting($xml)));

        return mb_strlen($text) > 220 ? rtrim(mb_substr($text, 0, 217)).'…' : $text;
    }
}
