<?php

namespace ErnestDefoe\Herald\Api;

use Carbon\Carbon;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A mailing's delivery records, 50 a page, optionally one status. Names come
 * from two lookups for the whole page, never one per row.
 */
class ListDeliveries extends Controller
{
    private const PER_PAGE = 50;
    private const STATUSES = ['queued', 'sent', 'failed', 'skipped', 'unknown'];

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = $this->mailing($request);
        $params = $request->getQueryParams();
        $page = max(1, (int) Arr::get($params, 'page', 1));
        $status = Arr::get($params, 'status');
        $db = resolve('db.connection');

        $query = $db->table('herald_deliveries')
            ->where('mailing_id', $mailing->id)
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status));

        $total = (clone $query)->count();
        $rows = $query->orderBy('id')->forPage($page, self::PER_PAGE)->get();

        $users = $db->table('users')->whereIn('id', $rows->pluck('user_id')->filter()->all())->pluck('username', 'id');
        $guests = $db->table('herald_subscribers')->whereIn('id', $rows->pluck('subscriber_id')->filter()->all())->pluck('email', 'id');

        return [
            'data' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'userId' => $row->user_id ? (int) $row->user_id : null,
                'username' => $row->user_id ? ($users[$row->user_id] ?? null) : null,
                'email' => $row->subscriber_id ? ($guests[$row->subscriber_id] ?? null) : null,
                'status' => $row->status,
                'attempts' => (int) $row->attempts,
                'error' => $row->error,
                'sentAt' => $row->sent_at ? Carbon::parse($row->sent_at, 'UTC')->toIso8601String() : null,
                'nextAttemptAt' => $row->next_attempt_at ? Carbon::parse($row->next_attempt_at, 'UTC')->toIso8601String() : null,
            ])->all(),
            'total' => $total,
            'perPage' => self::PER_PAGE,
            'outcomes' => (object) ($this->outcomes([$mailing->id])[$mailing->id] ?? []),
        ];
    }
}
