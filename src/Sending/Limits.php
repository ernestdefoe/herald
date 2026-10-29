<?php

namespace ErnestDefoe\Herald\Sending;

use Carbon\Carbon;
use ErnestDefoe\Herald\Settings;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\ConnectionInterface;

/**
 * Per-minute, per-hour and per-day sending limits, shared by every mailing.
 *
 * Counted from the delivery records (an indexed range on sent_at), so they
 * hold across mailings, drivers and restarts. While any limit is set, one
 * batch sends at a time across the whole forum, so two mailings cannot both
 * spend the same allowance.
 */
class Limits
{
    private const WINDOWS = [Settings::LIMIT_MINUTE => 60, Settings::LIMIT_HOUR => 3600, Settings::LIMIT_DAY => 86400];

    public function __construct(
        private SettingsRepositoryInterface $settings,
        private ConnectionInterface $db,
        private Cache $cache,
    ) {
    }

    public function active(): bool
    {
        foreach (array_keys(self::WINDOWS) as $key) {
            if ($this->limit($key) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * How many more may go out now: null when nothing is limited.
     */
    public function room(): ?int
    {
        $room = null;
        $now = Carbon::now();

        foreach (self::WINDOWS as $key => $seconds) {
            $limit = $this->limit($key);

            if ($limit > 0) {
                $used = $this->db->table('herald_deliveries')->where('sent_at', '>', $now->copy()->subSeconds($seconds))->count();
                $room = min($room ?? PHP_INT_MAX, max(0, $limit - $used));
            }
        }

        return $room;
    }

    /**
     * The forum-wide sending lock, or null when the cache cannot lock (then
     * each mailing's own lock is all there is).
     */
    public function lock(int $seconds): ?Lock
    {
        $store = $this->cache->getStore();

        return $store instanceof LockProvider ? $store->lock('herald.sending', $seconds) : null;
    }

    private function limit(string $key): int
    {
        return max(0, (int) $this->settings->get($key));
    }
}
