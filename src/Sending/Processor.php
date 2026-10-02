<?php

namespace ErnestDefoe\Herald\Sending;

use Carbon\Carbon;
use ErnestDefoe\Herald\Audience;
use ErnestDefoe\Herald\Locale;
use ErnestDefoe\Herald\Mail\Renderer;
use ErnestDefoe\Herald\Mail\Sender;
use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Settings;
use ErnestDefoe\Herald\Tag\TagRegistry;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sends one batch of a mailing.
 *
 * Three things can drive a send — the admin's open progress page, the queue,
 * and the scheduler — and any of them may be missing on a given host. Each
 * calls this, and the lock makes sure only one batch runs at a time, so two
 * drivers never mail the same member twice.
 */
class Processor
{
    /**
     * Long enough for any batch to finish; short enough that a batch which
     * died with the process does not hold the mailing for long.
     */
    private const LOCK_SECONDS = 120;

    public function __construct(
        private ConnectionInterface $db,
        private Audience $audience,
        private TagRegistry $tags,
        private Renderer $renderer,
        private Sender $sender,
        private Locale $locale,
        private SettingsRepositoryInterface $settings,
        private LoggerInterface $log,
    ) {
    }

    public function start(Mailing $mailing, User $actor): void
    {
        $mailing->status = Mailing::SENDING;
        $mailing->cursor = 0;
        $mailing->max_user_id = (int) User::query()->max('id');
        $mailing->recipient_total = $this->audience->recipients($mailing->filters ?? [])
            ->where('users.id', '<=', $mailing->max_user_id)
            ->count();
        $mailing->sent_count = 0;
        $mailing->failed_count = 0;
        $mailing->sent_by = $actor->id;
        $mailing->started_at = Carbon::now();
        $mailing->completed_at = null;
        $mailing->locked_until = null;
        $mailing->last_batch_at = null;
        $mailing->save();
    }

    /**
     * @return array{state: string, wait?: int} state is one of
     *   'sent'    — finished (or nothing left to do)
     *   'more'    — a batch went out and there is more to send
     *   'busy'    — another driver holds the batch, or the delay has not passed
     *   'stopped' — the mailing is not sending (cancelled, or a draft)
     */
    public function batch(Mailing $mailing): array
    {
        $delay = $this->delay();

        if (! $this->claim($mailing->id, $delay)) {
            $mailing->refresh();

            return $mailing->isSending() ? ['state' => 'busy', 'wait' => max(1, $delay)] : ['state' => 'stopped'];
        }

        $mailing->refresh();

        try {
            $users = $this->audience->recipients($mailing->filters ?? [])
                ->where('users.id', '>', $mailing->cursor)
                ->where('users.id', '<=', $mailing->max_user_id)
                ->orderBy('users.id')
                ->limit($this->batchSize())
                ->get();

            if ($users->isEmpty()) {
                $mailing->status = Mailing::SENT;
                $mailing->completed_at = Carbon::now();

                return ['state' => 'sent'];
            }

            $values = $this->tags->values($users);
            $urlTags = $this->tags->urlTags();

            foreach ($users as $user) {
                // Re-read between sends: an admin who presses Cancel stops the
                // send at the next member, not the next batch.
                if ($this->db->table('herald_mailings')->where('id', $mailing->id)->value('status') !== Mailing::SENDING) {
                    $mailing->status = Mailing::CANCELLED;

                    return ['state' => 'stopped'];
                }

                try {
                    $template = $this->renderer->template($mailing, $this->locale->for($user));
                    $this->sender->send($user, $template, $values[$user->id] ?? [], $urlTags);
                    $mailing->sent_count++;
                } catch (Throwable $e) {
                    $mailing->failed_count++;
                    $this->log->error('[herald] Could not send mailing '.$mailing->id.' to user '.$user->id.': '.$e->getMessage());
                }

                // Saved per member, so a process that dies mid-batch resumes
                // after the last one actually sent instead of mailing the
                // batch again.
                $mailing->cursor = $user->id;
                $this->db->table('herald_mailings')->where('id', $mailing->id)->update([
                    'cursor' => $mailing->cursor,
                    'sent_count' => $mailing->sent_count,
                    'failed_count' => $mailing->failed_count,
                ]);
            }

            return ['state' => 'more', 'wait' => $delay];
        } finally {
            $mailing->locked_until = null;
            $mailing->last_batch_at = Carbon::now();
            $mailing->save();
        }
    }

    /**
     * Take the batch, atomically. One UPDATE either wins or does not.
     */
    private function claim(int $id, int $delay): bool
    {
        $now = Carbon::now();

        return $this->db->table('herald_mailings')
            ->where('id', $id)
            ->where('status', Mailing::SENDING)
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<', $now))
            ->when($delay > 0, fn ($q) => $q->where(
                fn ($q) => $q->whereNull('last_batch_at')->orWhere('last_batch_at', '<=', $now->copy()->subSeconds($delay))
            ))
            ->update(['locked_until' => $now->copy()->addSeconds(self::LOCK_SECONDS)]) === 1;
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
