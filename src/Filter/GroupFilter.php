<?php

namespace ErnestDefoe\Herald\Filter;

use Flarum\Group\Group;
use Illuminate\Database\Eloquent\Builder;

class GroupFilter implements Filter
{
    public function key(): string
    {
        return 'groups';
    }

    public function apply(Builder $query, array $config): void
    {
        $include = $this->ids($config['include'] ?? []);
        $exclude = array_values(array_diff($this->ids($config['exclude'] ?? []), [Group::MEMBER_ID]));

        // 🚨 Members is not a row in group_user — every account is in it
        // implicitly. Filtering on it with a join would match nobody, so
        // choosing it simply means "everyone".
        if ($include && ! in_array(Group::MEMBER_ID, $include, true)) {
            $query->whereExists(function ($sub) use ($include) {
                $sub->selectRaw('1')
                    ->from('group_user')
                    ->whereColumn('group_user.user_id', 'users.id')
                    ->whereIn('group_user.group_id', $include);
            });
        }

        if ($exclude) {
            $query->whereNotExists(function ($sub) use ($exclude) {
                $sub->selectRaw('1')
                    ->from('group_user')
                    ->whereColumn('group_user.user_id', 'users.id')
                    ->whereIn('group_user.group_id', $exclude);
            });
        }
    }

    private function ids(mixed $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    }
}
