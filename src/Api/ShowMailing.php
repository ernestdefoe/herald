<?php

namespace ErnestDefoe\Herald\Api;

use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

class ShowMailing extends Controller
{
    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        return ['data' => $this->serialize($this->mailing($request), true)];
    }
}
