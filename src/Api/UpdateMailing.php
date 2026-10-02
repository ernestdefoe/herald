<?php

namespace ErnestDefoe\Herald\Api;

use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

class UpdateMailing extends Controller
{
    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = $this->mailing($request);

        // Changing the words while they are going out would send two
        // different emails under one name.
        if ($mailing->isSending()) {
            throw new ValidationException(['status' => resolve('translator')->trans('ernestdefoe-herald.forum.errors.sending')]);
        }

        $this->fill($mailing, $this->body($request), $actor);
        $mailing->save();

        return ['data' => $this->serialize($mailing, true)];
    }
}
