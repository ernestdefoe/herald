<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Subscriber;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Guest newsletter subscribers, 50 a page, newest first, with a search on
 * the address.
 */
class ListSubscribers extends Controller
{
    private const PER_PAGE = 50;

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) Arr::get($params, 'page', 1));
        $search = mb_strtolower(trim((string) Arr::get($params, 'q', '')));

        $query = Subscriber::query()
            ->when($search !== '', fn ($q) => $q->where('email', 'like', '%'.addcslashes($search, '%_\\').'%'));

        $total = (clone $query)->count();

        return [
            'data' => $query->orderByDesc('id')->forPage($page, self::PER_PAGE)->get()->map(fn (Subscriber $s) => self::row($s))->all(),
            'total' => $total,
            'perPage' => self::PER_PAGE,
            'active' => Subscriber::query()->active()->count(),
        ];
    }

    public static function status(Subscriber $s): string
    {
        return match (true) {
            $s->unsubscribed_at !== null => 'unsubscribed',
            $s->confirmed_at !== null => 'confirmed',
            default => 'pending',
        };
    }

    private static function row(Subscriber $s): array
    {
        return [
            'id' => $s->id,
            'email' => $s->email,
            'status' => self::status($s),
            'createdAt' => $s->created_at?->toIso8601String(),
            'confirmedAt' => $s->confirmed_at?->toIso8601String(),
        ];
    }
}
