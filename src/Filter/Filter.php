<?php

namespace ErnestDefoe\Herald\Filter;

use Illuminate\Database\Eloquent\Builder;

/**
 * A way of narrowing who a mailing goes to — Invision's MemberFilter.
 *
 * Every filter is applied to every count and every send, handed its own slice
 * of the mailing's saved filters (an empty array when the admin left it
 * alone). A filter with nothing set should leave the query untouched, unless,
 * like suspension, it has a safe default of its own.
 *
 * 🚨 Qualify every column (`users.joined_at`, not `joined_at`). Another
 * filter may have joined a table that has a column of the same name, and the
 * bare name is then ambiguous — a 500, on whichever combination of filters an
 * admin happens to pick.
 */
interface Filter
{
    /**
     * The key this filter's settings are saved under.
     */
    public function key(): string;

    public function apply(Builder $query, array $config): void;
}
