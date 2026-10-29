<?php

namespace ErnestDefoe\Herald\Sending;

use Carbon\Carbon;
use ErnestDefoe\Herald\Mailing;
use Illuminate\Database\ConnectionInterface;

/**
 * Starts what is due, every minute, from Flarum's scheduler.
 *
 * Each start is claimed with one conditional UPDATE first, so two scheduler
 * runs that overlap (a slow host, two cron entries) cannot both start the
 * same mailing or both create the same issue.
 */
class Scheduler
{
    public function __construct(
        private ConnectionInterface $db,
        private Processor $processor,
        private Drivers $drivers,
    ) {
    }

    /**
     * @return Mailing[] what started (issues and one-off mailings)
     */
    public function run(): array
    {
        $now = Carbon::now();
        $started = [];

        $due = Mailing::query()
            ->where('status', Mailing::SCHEDULED)
            ->where('scheduled_at', '<=', $now)
            ->where('is_template', false)
            ->orderBy('scheduled_at')
            ->with('creator')
            ->get();

        foreach ($due as $mailing) {
            $issue = $mailing->repeat_every ? $this->issue($mailing, $now) : $this->once($mailing);

            if ($issue) {
                if ($this->processor->start($issue, $mailing->creator)) {
                    $this->drivers->kick($issue->id);
                }

                $started[] = $issue;
            }
        }

        return $started;
    }

    private function once(Mailing $mailing): ?Mailing
    {
        $claimed = $this->db->table('herald_mailings')
            ->where('id', $mailing->id)
            ->where('status', Mailing::SCHEDULED)
            ->update(['status' => Mailing::SENDING]);

        return $claimed ? $mailing : null;
    }

    /**
     * A series makes a new mailing for each issue, with its own deliveries,
     * and moves on to its next time. A run that was missed (cron down for a
     * day) sends one issue now, not a backlog of them.
     */
    private function issue(Mailing $series, Carbon $now): ?Mailing
    {
        $next = Schedule::next($series->schedule_anchor ?? $series->scheduled_at, $series->timezone ?: 'UTC', $series->repeat_every, $now);

        $claimed = $this->db->table('herald_mailings')
            ->where('id', $series->id)
            ->where('status', Mailing::SCHEDULED)
            ->where('scheduled_at', $series->getRawOriginal('scheduled_at'))
            ->update(['scheduled_at' => $next]);

        if (! $claimed) {
            return null;
        }

        $issue = $series->duplicate(['series_id' => $series->id, 'created_by' => $series->created_by]);
        $issue->save();

        return $issue;
    }
}
