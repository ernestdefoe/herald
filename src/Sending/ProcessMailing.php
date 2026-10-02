<?php

namespace ErnestDefoe\Herald\Sending;

use ErnestDefoe\Herald\Mailing;
use Flarum\Queue\AbstractJob;
use Illuminate\Contracts\Queue\Queue;

/**
 * The background driver: one batch, then queue itself again until done.
 *
 * Only dispatched when the queue is a real one. On the sync driver a job runs
 * inside the request that dispatched it, so a self-dispatching job would send
 * the whole mailing inside the admin's click — a timeout with half the list
 * sent and no record of which half.
 */
class ProcessMailing extends AbstractJob
{
    public function __construct(public int $mailingId)
    {
    }

    public function handle(Processor $processor, Queue $queue): void
    {
        $mailing = Mailing::query()->find($this->mailingId);

        if (! $mailing || ! $mailing->isSending()) {
            return;
        }

        $result = $processor->batch($mailing);

        if ($result['state'] === 'more' || $result['state'] === 'busy') {
            $wait = $result['state'] === 'busy' ? max(5, $result['wait'] ?? 0) : ($result['wait'] ?? 0);

            $wait > 0
                ? $queue->later($wait, new self($this->mailingId))
                : $queue->push(new self($this->mailingId));
        }
    }
}
