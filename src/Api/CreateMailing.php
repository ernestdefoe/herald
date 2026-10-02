<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Mailing;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

class CreateMailing extends Controller
{
    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = new Mailing();
        $mailing->subject = '';
        $mailing->status = Mailing::DRAFT;
        $mailing->created_by = $actor->id;

        $this->fill($mailing, $this->body($request), $actor);
        $mailing->save();

        return ['data' => $this->serialize($mailing, true)];
    }
}
