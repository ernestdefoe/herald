<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Subscriber;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Removes a guest subscriber and their delivery rows — the GDPR erasure.
 */
class DeleteSubscriber extends Controller
{
    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        Subscriber::query()->findOrFail((int) Arr::get($request->getQueryParams(), 'id'))->delete();

        return ['deleted' => true];
    }
}
