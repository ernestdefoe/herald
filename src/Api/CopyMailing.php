<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Mailing;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

class CopyMailing extends Controller
{
    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $original = $this->mailing($request);

        $copy = new Mailing();
        $copy->subject = mb_substr(resolve('translator')->trans('ernestdefoe-herald.forum.list.copy_of', ['subject' => $original->subject]), 0, 255);
        $copy->content = $original->content;
        $copy->filters = $original->filters;
        $copy->status = Mailing::DRAFT;
        $copy->created_by = $actor->id;
        $copy->save();

        return ['data' => $this->serialize($copy, true)];
    }
}
