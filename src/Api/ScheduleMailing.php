<?php

namespace ErnestDefoe\Herald\Api;

use Carbon\Carbon;
use DateTimeZone;
use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Sending\Schedule;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST schedules (once, or repeating); DELETE unschedules, back to a draft.
 *
 * The time arrives as the wall-clock time the admin picked plus the zone it
 * is in ("2026-11-02T09:00", "America/New_York") and is stored as UTC with
 * the zone beside it, which is what lets a weekly 9am stay 9am across DST.
 */
class ScheduleMailing extends Controller
{
    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = $this->mailing($request);
        $trans = fn (string $key) => resolve('translator')->trans('ernestdefoe-herald.forum.errors.'.$key);

        if ($request->getMethod() === 'DELETE') {
            if ($mailing->status === Mailing::SCHEDULED) {
                $mailing->status = Mailing::DRAFT;
                $mailing->scheduled_at = $mailing->schedule_anchor = null;
                $mailing->timezone = $mailing->repeat_every = null;
                $mailing->save();
            }

            return ['data' => $this->serialize($mailing, true)];
        }

        if (! in_array($mailing->status, [Mailing::DRAFT, Mailing::SCHEDULED, Mailing::SENT, Mailing::CANCELLED, Mailing::SKIPPED], true) || $mailing->is_template) {
            throw new ValidationException(['status' => $trans('not_schedulable')]);
        }

        if (trim($mailing->subject) === '') {
            throw new ValidationException(['subject' => $trans('subject_required')]);
        }

        if (! $mailing->hasBody()) {
            throw new ValidationException(['content' => $trans('content_required')]);
        }

        $body = $this->body($request);
        $zone = (string) ($body['timezone'] ?? '');
        $repeat = $body['repeat'] ?? null;

        if (! in_array($zone, DateTimeZone::listIdentifiers(), true)) {
            throw new ValidationException(['timezone' => $trans('timezone_invalid')]);
        }

        if ($repeat !== null && $repeat !== '' && ! in_array($repeat, Schedule::REPEATS, true)) {
            throw new ValidationException(['repeat' => $trans('repeat_invalid')]);
        }

        try {
            $at = Carbon::createFromFormat('Y-m-d\TH:i', (string) ($body['at'] ?? ''), $zone);
        } catch (Throwable) {
            $at = null;
        }

        if (! $at) {
            throw new ValidationException(['at' => $trans('time_invalid')]);
        }

        $at = $at->utc()->startOfMinute();

        if ($at->lt(Carbon::now()->addMinutes(5)->subSeconds(30)) || $at->gt(Carbon::now()->addYear())) {
            throw new ValidationException(['at' => $trans('time_range')]);
        }

        $mailing->status = Mailing::SCHEDULED;
        $mailing->scheduled_at = $at;
        $mailing->schedule_anchor = $at;
        $mailing->timezone = $zone;
        $mailing->repeat_every = $repeat ?: null;
        $mailing->save();

        return ['data' => $this->serialize($mailing, true)];
    }
}
