<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Audience;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

class CountRecipients extends Controller
{
    public function __construct(private Audience $audience)
    {
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        return $this->audience->count($this->filters($this->body($request)['filters'] ?? []));
    }
}
