<?php

namespace ErnestDefoe\Herald\Tag;

use Flarum\User\User;
use Illuminate\Support\Collection;

/**
 * A source of quick tags — Invision's BulkMail extension point.
 *
 * 🚨 values() is handed a whole BATCH of recipients, not one. A tag that needs
 * a query (a pick'em rank, a subscription's renewal date) costs one query per
 * batch this way, instead of one per email.
 */
interface TagProvider
{
    /**
     * The tags this provider fills, as `name => translation key` for the
     * description shown beside it in the Quick Tags panel. Names are written
     * without braces: `member_name`, not `{member_name}`.
     *
     * @return array<string, string>
     */
    public function tags(): array;

    /**
     * Which of those tags hold a URL, so one written inside a link's address
     * is put back as a URL rather than percent-encoded text.
     *
     * @return string[]
     */
    public function urlTags(): array;

    /**
     * Plain-text values for every recipient, keyed by user id. Escaping is
     * Herald's job, not the provider's.
     *
     * @param Collection<int, User> $users
     * @return array<int, array<string, string>>
     */
    public function values(Collection $users): array;
}
