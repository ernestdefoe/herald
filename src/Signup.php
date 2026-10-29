<?php

namespace ErnestDefoe\Herald;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\QueryException;
use Illuminate\Mail\Message;
use Illuminate\Support\HtmlString;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A guest's newsletter signup, double opt-in.
 *
 * 🚨 Whatever the address is — new, pending, already subscribed, a member's —
 * the person who typed it is told the same thing, so the form cannot be used
 * to find out who is on the list or who has an account. What differs is what
 * the OWNER of the address receives, which only they can read:
 *
 *   new, pending or unsubscribed → a confirmation link (nothing is sent to
 *                                  them until they click it)
 *   a member's address           → a note that their newsletter switch is on
 *                                  their Settings page
 *   already subscribed           → nothing
 */
class Signup
{
    public function __construct(
        private Mailer $mailer,
        private Factory $views,
        private TranslatorInterface $translator,
        private SettingsRepositoryInterface $settings,
        private UrlGenerator $url,
    ) {
    }

    public function request(string $email, string $locale): void
    {
        $grammar = User::query()->getQuery()->getGrammar();
        $member = User::query()->whereRaw('LOWER('.$grammar->wrap('users.email').') = ?', [$email])->first();

        if ($member) {
            $this->mail($email, 'member', $this->url->to('forum')->route('settings'));

            return;
        }

        $subscriber = Subscriber::query()->where('email', $email)->first() ?? new Subscriber();

        if ($subscriber->exists && $subscriber->isActive()) {
            return;
        }

        $subscriber->email = $email;
        $subscriber->locale = $locale;
        $token = $subscriber->issueToken();

        try {
            $subscriber->save();
        } catch (QueryException) {
            // The same address, in a request that arrived a moment earlier.
            return;
        }

        $this->mail($email, 'confirm', $this->url->to('forum')->route('herald.confirm', ['subscriber' => $subscriber->id, 'token' => $token]));
    }

    private function mail(string $email, string $kind, string $link): void
    {
        $forumTitle = (string) $this->settings->get('forum_title');
        $t = fn (string $key) => $this->translator->trans("ernestdefoe-herald.email.signup_{$kind}_{$key}", ['forumTitle' => $forumTitle, 'hours' => Subscriber::CONFIRM_HOURS]);

        $accent = (string) $this->settings->get('theme_primary_color');
        $data = ['accent' => preg_match('/^#[0-9a-f]{6}$/i', $accent) ? $accent : '#4d698e', 'title' => $t('subject'), 'text' => $t('text'), 'button' => $t('button'), 'link' => $link, 'footer' => $t('footer')];

        $this->mailer->send(
            [
                'html' => new HtmlString($this->views->make('ernestdefoe-herald::email.notice', $data)->render()),
                'text' => new HtmlString($data['text']."\n\n".$data['button'].': '.$link."\n\n".$data['footer']."\n"),
            ],
            ['userEmail' => $email, 'username' => $email],
            fn (Message $message) => $message->to($email)->subject($data['title'])
        );
    }
}
