<?php

namespace ErnestDefoe\Herald;

use ErnestDefoe\Herald\Filter\FilterRegistry;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who a mailing reaches.
 *
 * Three conditions are not filters, because an admin must not be able to
 * switch them off: a confirmed address, a member who has not opted out, and
 * nothing beyond the highest id at the moment Send was pressed.
 */
class Audience
{
    public function __construct(private FilterRegistry $filters)
    {
    }

    /**
     * Everyone the filters match, before consent is considered.
     *
     * @return Builder<User>
     */
    public function matching(array $filters): Builder
    {
        $query = User::query()->select('users.*');

        foreach ($this->filters->all() as $filter) {
            $config = $filters[$filter->key()] ?? [];
            $filter->apply($query, is_array($config) ? $config : []);
        }

        return $query;
    }

    /**
     * Everyone who will actually be sent it.
     *
     * @return Builder<User>
     */
    public function recipients(array $filters): Builder
    {
        return $this->matching($filters)
            ->where('users.is_email_confirmed', true)
            ->where('users.herald_subscribed', true);
    }

    /**
     * @return array{reach: int, optedOut: int, unconfirmed: int}
     */
    public function count(array $filters): array
    {
        return [
            'reach' => $this->recipients($filters)->count(),
            'optedOut' => $this->matching($filters)
                ->where('users.is_email_confirmed', true)
                ->where('users.herald_subscribed', false)
                ->count(),
            'unconfirmed' => $this->matching($filters)
                ->where('users.is_email_confirmed', false)
                ->count(),
        ];
    }
}
