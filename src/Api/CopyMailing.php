<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Mailing;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Copy (any mailing, sent ones too), Save as template, and Use a template.
 */
class CopyMailing extends Controller
{
    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $original = $this->mailing($request);
        $as = Arr::get($this->body($request), 'as', 'copy');

        $subject = match ($as) {
            'template', 'use' => $original->subject,
            default => mb_substr(resolve('translator')->trans('ernestdefoe-herald.forum.list.copy_of', ['subject' => $original->subject]), 0, 255),
        };

        $copy = $original->duplicate([
            'subject' => $subject,
            'created_by' => $actor->id,
            // A copy of a template is another template; using one makes a mailing.
            'is_template' => $as === 'template' || ($as === 'copy' && $original->is_template),
        ]);
        $copy->save();

        return ['data' => $this->serialize($copy, true)];
    }
}
