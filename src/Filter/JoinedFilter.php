<?php

namespace ErnestDefoe\Herald\Filter;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class JoinedFilter implements Filter
{
    public function key(): string
    {
        return 'joined';
    }

    public function apply(Builder $query, array $config): void
    {
        if ($after = $this->date($config['after'] ?? null)) {
            $query->where('users.joined_at', '>=', $after->startOfDay());
        }

        if ($before = $this->date($config['before'] ?? null)) {
            $query->where('users.joined_at', '<=', $before->endOfDay());
        }
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value) ?: null;
    }
}
