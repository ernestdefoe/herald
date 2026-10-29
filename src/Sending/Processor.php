<?php

namespace ErnestDefoe\Herald\Sending;

use Carbon\Carbon;
use ErnestDefoe\Herald\Audience;
use ErnestDefoe\Herald\Builder\Discussions;
use ErnestDefoe\Herald\Locale;
use ErnestDefoe\Herald\Mail\Renderer;
use ErnestDefoe\Herald\Mail\Sender;
use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Settings;
use ErnestDefoe\Herald\Subscriber;
use ErnestDefoe\Herald\Tag\TagRegistry;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sends one batch of a mailing.
 *
 * Three things can drive a send — the admin's open progress page, the queue,
 * and the scheduler — and any of them may be missing on a given host. Each
 * calls this, and the lock makes sure only one batch runs at a time.
 *
 * 🚨 Never twice. Each recipient's delivery row is inserted BEFORE their
 * message is handed over, and the table's unique keys let that happen once
 * per mailing. A copy whose process died between the hand-over and the
 * write-back is "unknown" and never retried automatically: it may well have
 * arrived, and a duplicate is worse than a gap.
 */
class Processor
{
    /**
     * Long enough for any batch to finish; short enough that a batch which
     * died with the process does not hold the mailing for long.
     */
    private const LOCK_SECONDS = 120;

    /** Minutes before the second and the third attempt at a failed copy. */
    public const RETRY_MINUTES = [5, 30];

    public function __construct(
        private ConnectionInterface $db,
        private Audience $audience,
        private TagRegistry $tags,
        private Renderer $renderer,
        private Sender $sender,
        private Locale $locale,
        private SettingsRepositoryInterface $settings,
        private LoggerInterface $log,
        private Discussions $discussions,
        private Limits $limits,
    ) {
    }

    /**
     * Whether a mailing that lists discussions has none to list right now.
     */
    public function nothingToSend(Mailing $mailing): bool
    {
        $frozen = $mailing->frozen;
        $mailing->frozen = null;

        try {
            return $this->discussions->empty($mailing, $this->discussions->lists($mailing));
        } finally {
            $mailing->frozen = $frozen;
        }
    }

    /**
     * Begins a send: freezes the discussion lists, counts the audience, and
     * starts a fresh delivery record.
     *
     * @return bool false when there was nothing to list and it was skipped
     */
    public function start(Mailing $mailing, ?User $actor): bool
    {
        $now = Carbon::now();

        $mailing->frozen = null;
        $lists = $this->discussions->lists($mailing);
        $mailing->frozen = $this->discussions->dynamic($mailing) ? $lists : null;
        $mailing->scheduled_at = $mailing->repeat_every ? $mailing->scheduled_at : null;
        $mailing->started_at = $now;
        $mailing->sent_by = $actor?->id;
        $mailing->sent_count = $mailing->failed_count = $mailing->skipped_count = 0;
        $mailing->locked_until = $mailing->last_batch_at = null;

        if ($this->discussions->empty($mailing, $lists)) {
            $mailing->status = Mailing::SKIPPED;
            $mailing->recipient_total = 0;
            $mailing->completed_at = $now;
            $mailing->save();

            return false;
        }

        // Sending again starts a new record: the earlier run's rows would
        // otherwise read as "already sent" and stop every copy.
        $this->deliveries()->where('mailing_id', $mailing->id)->delete();

        $filters = $mailing->filters ?? [];

        $mailing->status = Mailing::SENDING;
        $mailing->cursor = $mailing->subscriber_cursor = 0;
        $mailing->max_user_id = (int) User::query()->max('id');
        $mailing->max_subscriber_id = $this->audience->includesGuests($filters) ? (int) Subscriber::query()->max('id') : 0;
        $mailing->recipient_total = $this->audience->recipients($filters)->where('users.id', '<=', $mailing->max_user_id)->count()
            + ($mailing->max_subscriber_id ? $this->audience->guests()->where('herald_subscribers.id', '<=', $mailing->max_subscriber_id)->count() : 0);
        $mailing->completed_at = null;
        $mailing->save();

        return true;
    }

    /**
     * Sends again to every copy that finally failed, once the mailing is done.
     */
    public function resendFailed(Mailing $mailing): int
    {
        $count = $this->deliveries()->where('mailing_id', $mailing->id)->where('status', 'failed')
            ->update(['next_attempt_at' => Carbon::now(), 'attempts' => 0, 'updated_at' => Carbon::now()]);

        if ($count) {
            // Only the failed copies: the main pass stays finished.
            $mailing->cursor = $mailing->max_user_id;
            $mailing->subscriber_cursor = $mailing->max_subscriber_id;
            $mailing->status = Mailing::SENDING;
            $mailing->completed_at = null;
            $mailing->save();
        }

        return $count;
    }

    /**
     * @return array{state: string, wait?: int} state is one of
     *   'sent'    — finished (or nothing left to do)
     *   'more'    — a batch went out and there is more to send
     *   'busy'    — another driver holds the batch, or the delay has not passed
     *   'limited' — a sending limit is used up for now
     *   'stopped' — the mailing is not sending (cancelled, or a draft)
     */
    public function batch(Mailing $mailing): array
    {
        $delay = $this->delay();
        $lock = $this->limits->active() ? $this->limits->lock(self::LOCK_SECONDS) : null;

        if ($lock && ! $lock->get()) {
            return ['state' => 'busy', 'wait' => 2];
        }

        try {
            return $this->claimed($mailing, $delay);
        } finally {
            $lock?->release();
        }
    }

    private function claimed(Mailing $mailing, int $delay): array
    {
        if (! $this->claim($mailing->id, $delay)) {
            $mailing->refresh();

            return $mailing->isSending() ? ['state' => 'busy', 'wait' => max(1, $delay)] : ['state' => 'stopped'];
        }

        $mailing->refresh();
        $now = Carbon::now();

        // Whatever is still queued was in flight when its process died.
        $this->deliveries()->where('mailing_id', $mailing->id)->where('status', 'queued')
            ->update(['status' => 'unknown', 'updated_at' => $now]);

        try {
            $room = $this->limits->room();

            if ($room === 0) {
                return ['state' => 'limited', 'wait' => 30];
            }

            $size = min($this->batchSize(), $room ?? PHP_INT_MAX);
            $retrying = false;
            $recipients = $mailing->isSending() ? $this->fresh($mailing, $size) : [];

            if (! $recipients) {
                $recipients = $this->due($mailing, $size);
                $retrying = true;
            }

            if (! $recipients) {
                if ($mailing->isSending()) {
                    $mailing->status = Mailing::SENT;
                    $mailing->completed_at = $now;
                }

                return ['state' => 'sent'];
            }

            $status = $mailing->status;

            foreach ($recipients as $recipient) {
                // Re-read between sends: an admin who presses Cancel stops the
                // send at the next recipient, not the next batch.
                if ($this->db->table('herald_mailings')->where('id', $mailing->id)->value('status') !== $status) {
                    $mailing->status = Mailing::CANCELLED;

                    return ['state' => 'stopped'];
                }

                $outcome = $this->deliver($mailing, $recipient, $retrying);

                match ($outcome) {
                    'sent' => $mailing->sent_count++,
                    'failed' => $retrying ? null : $mailing->failed_count++,
                    'skipped' => $mailing->skipped_count++,
                    default => null,
                };

                if ($retrying && in_array($outcome, ['sent', 'skipped'], true)) {
                    $mailing->failed_count = max(0, $mailing->failed_count - 1);
                }

                if (! $retrying) {
                    $recipient->guest ? $mailing->subscriber_cursor = $recipient->id : $mailing->cursor = $recipient->id;
                }
            }

            return ['state' => 'more', 'wait' => $delay];
        } finally {
            $mailing->locked_until = null;
            $mailing->last_batch_at = Carbon::now();
            $mailing->save();
        }
    }

    /**
     * The next recipients who have not been sent it: members first, by id,
     * then guest subscribers, by id.
     *
     * 🚨 A cursor on the id, never an offset: somebody joining mid-send
     * shifts every offset after them, which mails one person twice and skips
     * another.
     *
     * @return Recipient[]
     */
    private function fresh(Mailing $mailing, int $size): array
    {
        if ($mailing->cursor < $mailing->max_user_id) {
            $users = $this->audience->recipients($mailing->filters ?? [])
                ->where('users.id', '>', $mailing->cursor)
                ->where('users.id', '<=', $mailing->max_user_id)
                ->orderBy('users.id')
                ->limit($size)
                ->get();

            if ($users->isNotEmpty()) {
                return $this->members($users);
            }

            $mailing->cursor = $mailing->max_user_id;
        }

        if ($mailing->subscriber_cursor < $mailing->max_subscriber_id) {
            $guests = $this->audience->guests()
                ->where('herald_subscribers.id', '>', $mailing->subscriber_cursor)
                ->where('herald_subscribers.id', '<=', $mailing->max_subscriber_id)
                ->orderBy('herald_subscribers.id')
                ->limit($size)
                ->get();

            if ($guests->isNotEmpty()) {
                return $guests->map(fn (Subscriber $s) => $this->guest($s))->all();
            }

            $mailing->subscriber_cursor = $mailing->max_subscriber_id;
        }

        return [];
    }

    /**
     * Failed copies whose retry is due. Two queries for the people, whatever
     * the batch size.
     *
     * @return Recipient[]
     */
    private function due(Mailing $mailing, int $size): array
    {
        $rows = $this->deliveries()
            ->where('mailing_id', $mailing->id)
            ->where('status', 'failed')
            ->where('next_attempt_at', '<=', Carbon::now())
            ->orderBy('id')
            ->limit($size)
            ->get(['user_id', 'subscriber_id', 'attempts']);

        if ($rows->isEmpty()) {
            return [];
        }

        $attempts = [];
        foreach ($rows as $row) {
            $attempts[$row->user_id ? 'u'.$row->user_id : 's'.$row->subscriber_id] = (int) $row->attempts;
        }

        $users = User::query()->whereIn('id', $rows->pluck('user_id')->filter()->all())->get();
        $recipients = $this->members($users, $attempts);

        foreach (Subscriber::query()->whereIn('id', $rows->pluck('subscriber_id')->filter()->all())->get() as $subscriber) {
            $recipients[] = $this->guest($subscriber, $attempts['s'.$subscriber->id] ?? 0);
        }

        return $recipients;
    }

    /**
     * @param iterable<User> $users
     * @return Recipient[]
     */
    private function members($users, array $attempts = []): array
    {
        $users = collect($users);
        $values = $this->tags->values($users);

        return $users->map(fn (User $user) => new Recipient(
            false,
            $user->id,
            (string) $user->email,
            (string) $user->display_name,
            $this->locale->for($user),
            $values[$user->id] ?? [],
            $attempts['u'.$user->id] ?? 0,
        ))->values()->all();
    }

    private function guest(Subscriber $subscriber, int $attempts = 0): Recipient
    {
        return new Recipient(true, $subscriber->id, $subscriber->email, '', $subscriber->locale ?: $this->locale->default(), $this->tags->guestValues($subscriber), $attempts);
    }

    /**
     * @return string sent | failed | skipped | duplicate | gone
     */
    private function deliver(Mailing $mailing, Recipient $recipient, bool $retrying): string
    {
        $now = Carbon::now();
        $key = ['mailing_id' => $mailing->id, $recipient->column() => $recipient->id];
        $attempts = $retrying ? $recipient->attempts + 1 : 1;

        if ($retrying) {
            // Claimed from "failed", so two drivers cannot both retry it.
            if (! $this->deliveries()->where($key)->where('status', 'failed')->update(['status' => 'queued', 'attempts' => $attempts, 'updated_at' => $now])) {
                return 'gone';
            }
        } elseif (! $this->deliveries()->insertOrIgnore($key + ['status' => 'queued', 'attempts' => 1, 'created_at' => $now, 'updated_at' => $now])) {
            return 'duplicate';
        }

        // Consent is checked again just before the copy goes: somebody who
        // unsubscribed a minute ago, mid-send, is not sent it.
        if (! $this->consents($recipient)) {
            $this->deliveries()->where($key)->update(['status' => 'skipped', 'next_attempt_at' => null, 'updated_at' => $now]);

            return 'skipped';
        }

        try {
            $template = $this->renderer->template($mailing, $recipient->locale, $recipient->guest);
            $this->sender->send($recipient->email, $recipient->name, $template, $recipient->values, $this->tags->urlTags());

            $this->deliveries()->where($key)->update(['status' => 'sent', 'sent_at' => Carbon::now(), 'error' => null, 'next_attempt_at' => null, 'updated_at' => Carbon::now()]);

            return 'sent';
        } catch (Throwable $e) {
            $retryIn = self::RETRY_MINUTES[$attempts - 1] ?? null;

            $this->deliveries()->where($key)->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 255),
                'next_attempt_at' => $retryIn ? Carbon::now()->addMinutes($retryIn) : null,
                'updated_at' => Carbon::now(),
            ]);

            $this->log->error('[herald] Could not send mailing '.$mailing->id.' to '.$recipient->column().' '.$recipient->id.': '.$e->getMessage());

            return 'failed';
        }
    }

    private function consents(Recipient $recipient): bool
    {
        if ($recipient->guest) {
            return Subscriber::query()->whereKey($recipient->id)->active()->exists();
        }

        return $this->db->table('users')
            ->where('id', $recipient->id)
            ->where('email', $recipient->email)
            ->where('is_email_confirmed', true)
            ->where('herald_subscribed', true)
            ->exists();
    }

    /**
     * Take the batch, atomically. One UPDATE either wins or does not.
     *
     * A finished mailing can still be claimed, for its due retries.
     */
    private function claim(int $id, int $delay): bool
    {
        $now = Carbon::now();

        return $this->db->table('herald_mailings')
            ->where('id', $id)
            ->whereIn('status', [Mailing::SENDING, Mailing::SENT])
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<', $now))
            ->when($delay > 0, fn ($q) => $q->where(
                fn ($q) => $q->whereNull('last_batch_at')->orWhere('last_batch_at', '<=', $now->copy()->subSeconds($delay))
            ))
            ->update(['locked_until' => $now->copy()->addSeconds(self::LOCK_SECONDS)]) === 1;
    }

    private function deliveries(): Builder
    {
        return $this->db->table('herald_deliveries');
    }

    public function batchSize(): int
    {
        return max(1, min(1000, (int) ($this->settings->get(Settings::BATCH_SIZE) ?: Settings::DEFAULT_BATCH_SIZE)));
    }

    public function delay(): int
    {
        return max(0, min(3600, (int) $this->settings->get(Settings::BATCH_DELAY)));
    }
}
