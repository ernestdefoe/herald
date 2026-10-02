<?php

namespace ErnestDefoe\Herald\Http;

use ErnestDefoe\Herald\Unsubscribe\Token;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The link in every Herald email. No sign-in, by design.
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
        private Factory $views,
        private UrlGenerator $url,
        private SettingsRepositoryInterface $settings,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $user = User::query()->find((int) Arr::get($params, 'user'));
        $token = (string) Arr::get($params, 'token');

        if (! $user || ! $this->token->verify($user, $token)) {
            return $this->page('invalid', null, 404);
        }

        if ($request->getMethod() === 'POST') {
            $resubscribe = Arr::get((array) $request->getParsedBody(), 'action') === 'resubscribe';

            $user->herald_subscribed = $resubscribe;
            $user->save();

            return $this->page($resubscribe ? 'resubscribed' : 'unsubscribed', $user);
        }

        return $this->page($user->herald_subscribed ? 'confirm' : 'unsubscribed', $user);
    }

    private function colour(string $value): string
    {
        return preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $value) ? $value : '#4d698e';
    }

    private function page(string $state, ?User $user, int $status = 200): ResponseInterface
    {
        return new HtmlResponse($this->views->make('ernestdefoe-herald::unsubscribe', [
            'state' => $state,
            'action' => $user ? $this->token->url($user) : null,
            'forumTitle' => (string) $this->settings->get('forum_title'),
            // The forum's own brand colour, so the page reads as the forum's
            // and not as some third party's.
            'accent' => $this->colour((string) $this->settings->get('theme_primary_color')),
            'forumUrl' => $this->url->to('forum')->base(),
            'settingsUrl' => $this->url->to('forum')->route('settings'),
            'translator' => $this->translator,
        ])->render(), $status);
    }
}
