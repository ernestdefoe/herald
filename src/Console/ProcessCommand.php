<?php

namespace ErnestDefoe\Herald\Console;

use Carbon\Carbon;
use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Sending\Processor;
use ErnestDefoe\Herald\Sending\Scheduler;
use ErnestDefoe\Herald\Subscriber;
use Flarum\Console\AbstractCommand;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The scheduler's driver, every minute: start what is due, send batches for
 * up to ~50 seconds — including any send whose queue job was lost — retry
 * failed copies whose time has come, and, once an hour, forget guest
 * addresses nobody confirmed.
 */
class ProcessCommand extends AbstractCommand
{
    private const BUDGET_SECONDS = 50;

    public function __construct(
        private Processor $processor,
        private Scheduler $scheduler,
        private Cache $cache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('herald:process')
            ->setDescription('Start scheduled Herald mailings, send the next batches, and retry failed copies.');
    }

    protected function fire(): int
    {
        $deadline = microtime(true) + self::BUDGET_SECONDS;

        foreach ($this->scheduler->run() as $issue) {
            $this->output->writeln(sprintf('Mailing %d started (%s).', $issue->id, $issue->status));
        }

        $mailings = Mailing::query()
            ->where('status', Mailing::SENDING)
            ->orWhere(fn ($q) => $q->where('status', Mailing::SENT)->whereExists(
                fn ($sub) => $sub->selectRaw('1')->from('herald_deliveries')
                    ->whereColumn('herald_deliveries.mailing_id', 'herald_mailings.id')
                    ->where('herald_deliveries.status', 'failed')
                    ->where('herald_deliveries.next_attempt_at', '<=', Carbon::now())
            ))
            ->orderBy('id')
            ->get();

        foreach ($mailings as $mailing) {
            while (microtime(true) < $deadline) {
                $result = $this->processor->batch($mailing);

                if ($result['state'] !== 'more') {
                    break;
                }

                if (($result['wait'] ?? 0) > 0) {
                    // A delay longer than what is left of this run is the
                    // next run's job.
                    if (microtime(true) + $result['wait'] >= $deadline) {
                        break;
                    }

                    sleep($result['wait']);
                }
            }

            $this->output->writeln(sprintf('Mailing %d: %d sent, %d failed, %d skipped (%s).', $mailing->id, $mailing->sent_count, $mailing->failed_count, $mailing->skipped_count, $mailing->status));
        }

        if ($this->cache->add('herald.purged', 1, 3600)) {
            Subscriber::query()->whereNull('confirmed_at')->where('created_at', '<', Carbon::now()->subDays(Subscriber::PURGE_DAYS))->delete();
        }

        return 0;
    }
}
