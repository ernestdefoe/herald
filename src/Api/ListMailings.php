<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Mailing;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

class ListMailings extends Controller
{
    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailings = Mailing::query()->with('creator')->orderByDesc('id')->limit(200)->get();

        return ['data' => $mailings->map(fn (Mailing $m) => $this->serialize($m))->all()];
    }
}
