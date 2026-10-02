<?php

namespace ErnestDefoe\Herald\Unsubscribe;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

/**
 * A signed, sign-in-free unsubscribe link.
 *
 * The signature covers the id AND the email, so a link stops working when the
 * address it was sent to is no longer the member's. The secret lives in a
 * setting, minted the first time it is needed.
 */
class Token
{
    public const SECRET = 'ernestdefoe-herald.secret';

    public function __construct(
        private SettingsRepositoryInterface $settings,
        private UrlGenerator $url,
    ) {
    }

    public function make(User $user): string
    {
        return substr(hash_hmac('sha256', $user->id.'|'.mb_strtolower((string) $user->email), $this->secret()), 0, 40);
    }

    public function verify(User $user, string $token): bool
    {
        return hash_equals($this->make($user), $token);
    }

    public function url(User $user): string
    {
        return $this->url->to('forum')->route('herald.unsubscribe', [
            'user' => $user->id,
            'token' => $this->make($user),
        ]);
    }

    private function secret(): string
    {
        $secret = (string) $this->settings->get(self::SECRET);

        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            $this->settings->set(self::SECRET, $secret);
        }

        return $secret;
    }
}
