<?php

namespace ErnestDefoe\Herald\Http;

use Carbon\Carbon;
use ErnestDefoe\Herald\Subscriber;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The link in a guest's confirmation email.
 *
 * GET asks and POST confirms, for the same reason as unsubscribing: a mail
 * scanner that opens every link must not be able to confirm an address on
 * its owner's behalf — that would make the opt-in single, not double.
 *
 * The token is single-use and expires; only its hash is stored, compared in
 * constant time.
 */
class ConfirmController implements RequestHandlerInterface
{
    public function __construct(private Page $page)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $subscriber = Subscriber::query()->find((int) Arr::get($params, 'subscriber'));
        $token = (string) Arr::get($params, 'token');

        if (! $subscriber || ! $subscriber->tokenMatches($token)) {
            return $this->page->render('signup_invalid', [], 404);
        }

        if ($request->getMethod() !== 'POST') {
            return $this->page->render('signup_confirm', ['action' => (string) $request->getUri(), 'button' => 'signup_confirm_button']);
        }

        $subscriber->confirmed_at = Carbon::now();
        $subscriber->unsubscribed_at = null;
        $subscriber->token_hash = null;
        $subscriber->token_expires_at = null;
        $subscriber->save();

        return $this->page->render('signup_done');
    }
}
