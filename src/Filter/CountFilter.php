<?php

namespace ErnestDefoe\Herald\Filter;

use Illuminate\Database\Eloquent\Builder;

/**
 * At least / at most N of something counted on the users row.
 */
class CountFilter implements Filter
{
    public function __construct(
        private string $key,
        private string $column,
    ) {
    }

    public static function posts(): self
    {
        return new self('posts', 'users.comment_count');
    }

    public static function discussions(): self
    {
        return new self('discussions', 'users.discussion_count');
    }

    public function key(): string
    {
        return $this->key;
    }

    public function apply(Builder $query, array $config): void
    {
        if (isset($config['min']) && $config['min'] !== '') {
            $query->where($this->column, '>=', max(0, (int) $config['min']));
        }

        if (isset($config['max']) && $config['max'] !== '') {
            $query->where($this->column, '<=', max(0, (int) $config['max']));
        }
    }
}
