<?php

namespace ErnestDefoe\Herald\Api;

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

        $mailing->delete();

        return ['deleted' => true];
    }
}
