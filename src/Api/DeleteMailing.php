<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Mailing;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

class DeleteMailing extends Controller
{
    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = $this->mailing($request);

        if ($mailing->isSending()) {
            throw new ValidationException(['status' => resolve('translator')->trans('ernestdefoe-herald.forum.errors.sending')]);
        }

        // A deleted series leaves its issues as ordinary mailings, with their
        // delivery records, rather than taking them with it.
        Mailing::query()->where('series_id', $mailing->id)->update(['series_id' => null]);

        $mailing->delete();

        return ['deleted' => true];
    }
}
