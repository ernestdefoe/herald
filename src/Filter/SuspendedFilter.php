<?php

namespace ErnestDefoe\Herald\Filter;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Suspended members are left out unless the admin asks for them.
 *
 * The column only exists while flarum/suspend is installed, so this filter
 * asks before it uses it.
 */
class SuspendedFilter implements Filter
{
    private ?bool $hasColumn = null;

    public function __construct(private ConnectionInterface $db)
    {
    }

    public function key(): string
    {
        return 'suspended';
    }

    public function apply(Builder $query, array $config): void
    {
        if (! empty($config['include']) || ! $this->hasColumn()) {
            return;
        }

        $query->where(fn ($q) => $q->whereNull('users.suspended_until')->orWhere('users.suspended_until', '<', Carbon::now()));
    }

    private function hasColumn(): bool
    {
        return $this->hasColumn ??= $this->db->getSchemaBuilder()->hasColumn('users', 'suspended_until');
    }
}
