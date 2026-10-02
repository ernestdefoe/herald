<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Sending\Processor;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The progress page's driver — Invision's MultipleRedirect. While an admin
 * watches, their browser pushes the send along one batch per request, so a
 * forum with no queue worker and no cron still finishes its mailing.
 */
class ProcessBatch extends Controller
{
    public function __construct(private Processor $processor)
    {
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = $this->mailing($request);

        $result = $mailing->isSending() ? $this->processor->batch($mailing) : ['state' => 'stopped'];

        $mailing->refresh();

        return $result + ['data' => $this->serialize($mailing)];
    }
}
