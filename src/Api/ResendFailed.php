<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Sending\Drivers;
use ErnestDefoe\Herald\Sending\Processor;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * "Resend to failed": once a mailing has finished, the copies that finally
 * failed go again. Copies whose outcome is unknown are not among them — they
 * may have arrived.
 */
class ResendFailed extends Controller
{
    public function __construct(
        private Processor $processor,
        private Drivers $drivers,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = $this->mailing($request);

        if (! in_array($mailing->status, [Mailing::SENT, Mailing::CANCELLED], true)) {
            throw new ValidationException(['status' => resolve('translator')->trans('ernestdefoe-herald.forum.errors.not_finished')]);
        }

        if ($this->processor->resendFailed($mailing)) {
            $this->drivers->kick($mailing->id);
        }

        return ['data' => $this->serialize($mailing), 'background' => $this->drivers->background()];
    }
}
