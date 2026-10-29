<?php

namespace ErnestDefoe\Herald\Sending;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * When a repeating mailing goes next.
 *
 * Counted from the FIRST send time, in the zone it was chosen in, a whole
 * number of days, weeks or months on. So "Mondays at 9:00 New York" is 9:00
 * in New York on both sides of a clock change (13:00 UTC, then 14:00), and a
 * monthly send on the 31st goes on the last day of shorter months and comes
 * back to the 31st after, rather than drifting to the 28th for good.
 */
final class Schedule
{
    public const REPEATS = ['daily', 'weekly', 'monthly'];

    public static function next(CarbonInterface $anchor, string $timezone, string $every, CarbonInterface $after): Carbon
    {
        $local = Carbon::instance($anchor)->setTimezone($timezone);

        // Jump close to the answer, then step: a daily series years old does
        // not need a thousand iterations.
        $k = $after->gt($anchor) ? max(0, (int) floor(match ($every) {
            'daily' => abs($anchor->diffInDays($after)),
            'weekly' => abs($anchor->diffInWeeks($after)),
            default => abs($anchor->diffInMonths($after)),
        }) - 1) : 0;

        do {
            $k++;
            $next = match ($every) {
                'daily' => $local->copy()->addDays($k),
                'weekly' => $local->copy()->addWeeks($k),
                default => $local->copy()->addMonthsNoOverflow($k),
            };
        } while ($next->lte($after));

        return $next->utc();
    }
}
