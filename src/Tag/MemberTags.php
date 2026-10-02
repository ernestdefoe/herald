<?php

namespace ErnestDefoe\Herald\Tag;

use ErnestDefoe\Herald\Locale;
use Illuminate\Support\Collection;

class MemberTags implements TagProvider
{
    public function __construct(private Locale $locale)
    {
    }

    public function tags(): array
    {
        return [
            'member_id' => 'ernestdefoe-herald.forum.tags.member_id',
            'member_name' => 'ernestdefoe-herald.forum.tags.member_name',
            'member_username' => 'ernestdefoe-herald.forum.tags.member_username',
            'member_joined' => 'ernestdefoe-herald.forum.tags.member_joined',
            'member_last_visit' => 'ernestdefoe-herald.forum.tags.member_last_visit',
            'member_posts' => 'ernestdefoe-herald.forum.tags.member_posts',
            'member_discussions' => 'ernestdefoe-herald.forum.tags.member_discussions',
        ];
    }

    public function urlTags(): array
    {
        return [];
    }

    public function values(Collection $users): array
    {
        $values = [];

        foreach ($users as $user) {
            $locale = $this->locale->for($user);

            $values[$user->id] = [
                'member_id' => (string) $user->id,
                'member_name' => (string) $user->display_name,
                'member_username' => (string) $user->username,
                // Formatted in the RECIPIENT's language. A German member reads
                // "2. Oktober 2026", not "October 2, 2026".
                'member_joined' => $user->joined_at ? $user->joined_at->copy()->locale($locale)->isoFormat('LL') : '',
                'member_last_visit' => $user->last_seen_at ? $user->last_seen_at->copy()->locale($locale)->isoFormat('LL') : '',
                'member_posts' => number_format((int) $user->comment_count),
                'member_discussions' => number_format((int) $user->discussion_count),
            ];
        }

        return $values;
    }
}
