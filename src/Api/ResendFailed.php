<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Builder\Blocks;
use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Sending\Drivers;
use ErnestDefoe\Herald\Sending\Processor;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * "Resend to failed": once a mailing has finished, the copies that finally
 * failed go again. Copies whose outcome is unknown are not among them — they
 * may have arrived.
 */
class ResendFailed extends Controller
{
    public function __construct(
        TranslatorInterface $translator,
        Blocks $blocks,
        private Processor $processor,
        private Drivers $drivers,
    ) {
        parent::__construct($translator, $blocks);
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = $this->mailing($request);

        if (! in_array($mailing->status, [Mailing::SENT, Mailing::CANCELLED], true)) {
            throw new ValidationException(['status' => $this->translator->trans('ernestdefoe-herald.forum.errors.not_finished')]);
        }

        if ($this->processor->resendFailed($mailing)) {
            $this->drivers->kick($mailing->id);
        }

        return ['data' => $this->serialize($mailing), 'background' => $this->drivers->background()];
    }
}
