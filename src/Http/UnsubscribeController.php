<?php

namespace ErnestDefoe\Herald\Http;

use Carbon\Carbon;
use ErnestDefoe\Herald\Subscriber;
use ErnestDefoe\Herald\Unsubscribe\Token;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The link in every Herald email — a member's, or a guest subscriber's. No
 * sign-in, by design.
 *
 *   GET  — asks. Mail scanners and link previewers fetch every link in a
 *          message; a GET that unsubscribed would unsubscribe people who
 *          never clicked anything.
 *   POST — does it. This is also what RFC 8058's one-click header calls, so
 *          it answers without asking again.
 *
 * Both POSTs are exempt from CSRF: the signed token is the proof, and a mail
 * client's one-click request carries no session.
 */
class UnsubscribeController implements RequestHandlerInterface
{
    public function __construct(
        private Token $token,
        private Page $page,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $token = (string) Arr::get($params, 'token');
        $resubscribe = Arr::get((array) $request->getParsedBody(), 'action') === 'resubscribe';
        $post = $request->getMethod() === 'POST';

        if (Arr::has($params, 'subscriber')) {
            $subscriber = Subscriber::query()->find((int) Arr::get($params, 'subscriber'));

            if (! $subscriber || $subscriber->confirmed_at === null || ! $this->token->verifySubscriber($subscriber, $token)) {
                return $this->page->render('guest_invalid', [], 404);
            }

            if ($post) {
                $subscriber->unsubscribed_at = $resubscribe ? null : ($subscriber->unsubscribed_at ?? Carbon::now());
                $subscriber->save();
            }

            return $this->state($subscriber->unsubscribed_at === null, $post && $resubscribe, $this->token->subscriberUrl($subscriber), true);
        }

        $user = User::query()->find((int) Arr::get($params, 'user'));

        if (! $user || ! $this->token->verify($user, $token)) {
            return $this->page->render('invalid', ['link' => $this->page->settingsUrl(), 'linkLabel' => 'manage'], 404);
        }

        if ($post) {
            $user->setAttribute('herald_subscribed', $resubscribe);
            $user->save();
        }

        return $this->state((bool) $user->getAttribute('herald_subscribed'), $post && $resubscribe, $this->token->url($user), false);
    }

    private function state(bool $subscribed, bool $justResubscribed, string $action, bool $guest): ResponseInterface
    {
        $prefix = $guest ? 'guest_' : '';

        if ($justResubscribed) {
            return $this->page->render($prefix.'resubscribed');
        }

        if ($subscribed) {
            return $this->page->render($prefix.'confirm', ['action' => $action, 'button' => 'confirm_button']);
        }

        return $this->page->render($prefix.'done', ['action' => $action, 'button' => 'resubscribe_button', 'formAction' => 'resubscribe', 'secondary' => true]);
    }
}
