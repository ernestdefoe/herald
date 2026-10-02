<?php

namespace ErnestDefoe\Herald\Api;

use Carbon\Carbon;
use ErnestDefoe\Herald\Mailing;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

class CancelMailing extends Controller
{
    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = $this->mailing($request);

        if ($mailing->isSending()) {
            $mailing->status = Mailing::CANCELLED;
            $mailing->completed_at = Carbon::now();
            $mailing->save();
        }

        return ['data' => $this->serialize($mailing)];
    }
}
