<?php

namespace ErnestDefoe\Herald\Sending;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\SyncQueue;

class Drivers
{
    public function __construct(
        private Queue $queue,
        private Cache $cache,
    ) {
    }

    public function hasQueue(): bool
    {
        return ! $this->queue instanceof SyncQueue;
    }

    /**
     * Flarum records when `schedule:run` last ran; recent means cron is set up.
     */
    public function hasScheduler(): bool
    {
        $last = $this->cache->get('flarum:schedule:last_run');

        return $last && Carbon::parse($last)->gt(Carbon::now()->subMinutes(5));
    }

    /**
     * Whether a send keeps going after the admin closes the page.
     */
    public function background(): bool
    {
        return $this->hasQueue() || $this->hasScheduler();
    }

    public function kick(int $mailingId): void
    {
        if ($this->hasQueue()) {
            $this->queue->push(new ProcessMailing($mailingId));
        }
    }
}
