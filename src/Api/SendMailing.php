<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Mailing;
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
        $trans = fn (string $key) => resolve('translator')->trans('ernestdefoe-herald.forum.errors.'.$key);

        if ($mailing->isSending()) {
            throw new ValidationException(['status' => $trans('sending')]);
        }

        if ($mailing->is_template) {
            throw new ValidationException(['status' => $trans('template')]);
        }

        if (trim($mailing->subject) === '') {
            throw new ValidationException(['subject' => $trans('subject_required')]);
        }

        if (! $mailing->hasBody()) {
            throw new ValidationException(['content' => $trans('content_required')]);
        }

        // Sent by hand, an empty discussions list is a mistake to point out,
        // not an issue to skip quietly.
        if ($this->processor->nothingToSend($mailing)) {
            throw new ValidationException(['content' => $trans('nothing_to_list')]);
        }

        // Send now on a repeating mailing sends one copy; the series keeps
        // its schedule.
        if ($mailing->repeat_every && $mailing->status === Mailing::SCHEDULED) {
            $issue = $mailing->duplicate(['series_id' => $mailing->id, 'created_by' => $actor->id]);
            $issue->save();
            $mailing = $issue;
        } else {
            $mailing->scheduled_at = $mailing->schedule_anchor = null;
            $mailing->timezone = $mailing->repeat_every = null;
        }

        $this->processor->start($mailing, $actor);
        $this->drivers->kick($mailing->id);

        return [
            'data' => $this->serialize($mailing),
            'background' => $this->drivers->background(),
        ];
    }
}
