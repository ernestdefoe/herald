<?php

namespace ErnestDefoe\Herald\Filter;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Active within N days, or away for at least N days — the "win them back"
 * mailing, and the filter Invision's own users asked for most.
 */
class LastVisitFilter implements Filter
{
    public function key(): string
    {
        return 'lastVisit';
    }

    public function apply(Builder $query, array $config): void
    {
        $days = (int) ($config['days'] ?? 0);

        if ($days <= 0) {
            return;
        }

        $since = Carbon::now()->subDays($days);

        if (($config['mode'] ?? 'active') === 'inactive') {
            // A member who has never been seen at all has certainly not been
            // seen recently.
            $query->where(fn ($q) => $q->whereNull('users.last_seen_at')->orWhere('users.last_seen_at', '<', $since));
        } else {
            $query->where('users.last_seen_at', '>=', $since);
        }
    }
}
