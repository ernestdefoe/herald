<?php

namespace ErnestDefoe\Herald\Console;

use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Sending\Processor;
use Flarum\Console\AbstractCommand;

/**
 * The scheduler's driver, for forums without a queue worker: every minute,
 * send batches for up to ~50 seconds.
 */
class ProcessCommand extends AbstractCommand
{
    private const BUDGET_SECONDS = 50;

    public function __construct(private Processor $processor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('herald:process')
            ->setDescription('Send the next batches of any Herald mailing that is sending.');
    }

    protected function fire(): int
    {
        $deadline = microtime(true) + self::BUDGET_SECONDS;

        foreach (Mailing::query()->where('status', Mailing::SENDING)->orderBy('id')->get() as $mailing) {
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

            $this->output->writeln(sprintf('Mailing %d: %d sent, %d failed (%s).', $mailing->id, $mailing->sent_count, $mailing->failed_count, $mailing->status));
        }

        return 0;
    }
}
