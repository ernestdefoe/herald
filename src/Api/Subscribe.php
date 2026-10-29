<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Settings;
use ErnestDefoe\Herald\Signup;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The signup widget's form, for guests. Members use their own switch.
 *
 * 🚨 One answer for every address (see Signup). Rate-limited per IP — the
 * key is a hash, no address is kept — and per email address, where going
 * over is silent so the limit itself reveals nothing. A hidden "website"
 * field catches form-filling bots: filled in, it is thanked and ignored.
 */
class Subscribe implements RequestHandlerInterface
{
    private const PER_IP_HOUR = 5;
    private const PER_EMAIL_DAY = 3;

    public function __construct(
        private Signup $signup,
        private Cache $cache,
        private SettingsRepositoryInterface $settings,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (! RequestUtil::getActor($request)->isGuest() || $this->settings->get(Settings::GUEST_SIGNUP) === '0') {
            throw new PermissionDeniedException();
        }

        $body = (array) $request->getParsedBody();
        $done = new JsonResponse(['ok' => true]);

        if (trim((string) ($body['website'] ?? '')) !== '') {
            return $done;
        }

        $email = mb_strtolower(trim((string) ($body['email'] ?? '')));

        if (mb_strlen($email) > 150 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['email' => $this->translator->trans('ernestdefoe-herald.forum.signup.invalid')]);
        }

        $ip = (string) ($request->getAttribute('ipAddress') ?? '');

        if (! $this->allow('ip.'.hash('sha256', $ip), self::PER_IP_HOUR, 3600)) {
            return new JsonResponse(['errors' => [['status' => '429', 'code' => 'too_many_requests', 'detail' => $this->translator->trans('ernestdefoe-herald.forum.signup.too_many')]]], 429);
        }

        if ($this->allow('email.'.hash('sha256', $email), self::PER_EMAIL_DAY, 86400)) {
            $this->signup->request($email, $this->translator->getLocale());
        }

        return $done;
    }

    private function allow(string $key, int $max, int $seconds): bool
    {
        $key = 'herald.signup.'.$key;
        $this->cache->add($key, 0, $seconds);

        return (int) $this->cache->increment($key) <= $max;
    }
}
