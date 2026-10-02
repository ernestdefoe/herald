<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Sending\Drivers;
use ErnestDefoe\Herald\Sending\Processor;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

class SendMailing extends Controller
{
    public function __construct(
        private Processor $processor,
        private Drivers $drivers,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = $this->mailing($request);
        $translator = resolve('translator');

        if ($mailing->isSending()) {
            throw new ValidationException(['status' => $translator->trans('ernestdefoe-herald.forum.errors.sending')]);
        }

        if (trim($mailing->subject) === '') {
            throw new ValidationException(['subject' => $translator->trans('ernestdefoe-herald.forum.errors.subject_required')]);
        }

        if (! $mailing->content) {
            throw new ValidationException(['content' => $translator->trans('ernestdefoe-herald.forum.errors.content_required')]);
        }

        $this->processor->start($mailing, $actor);
        $this->drivers->kick($mailing->id);

        return [
            'data' => $this->serialize($mailing),
            'background' => $this->drivers->background(),
        ];
    }
}
