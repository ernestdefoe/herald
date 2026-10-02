<?php

namespace ErnestDefoe\Herald\Mail;

use ErnestDefoe\Herald\Mailing;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\Translator;
use Flarum\Mail\SafeSubstitution;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\HtmlString;
use Symfony\Contracts\Translation\TranslatorInterface;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Renders a mailing once per language.
 *
 * The body is the same for everyone; only the footer's language differs. So a
 * send renders a handful of templates and personalises copies of them, rather
 * than rendering five thousand emails.
 */
class Renderer
{
    /** @var array<string, Template> */
    private array $cache = [];

    public function __construct(
        private Factory $views,
        private TranslatorInterface $translator,
        private SettingsRepositoryInterface $settings,
        private UrlGenerator $url,
        private EmailHtml $emailHtml,
        private PlainText $plainText,
    ) {
    }

    public function template(Mailing $mailing, string $locale): Template
    {
        $key = $mailing->id.'|'.$mailing->updated_at?->getTimestamp().'|'.$locale;

        return $this->cache[$key] ??= $this->render($mailing, $locale);
    }

    private function render(Mailing $mailing, string $locale): Template
    {
        $body = $this->emailHtml->prepare($mailing->renderBody(), $this->url->to('forum')->base());

        $previous = $this->translator->getLocale();
        $this->setLocale($locale);

        try {
            $data = [
                'subject' => $mailing->subject,
                'body' => new HtmlString($body),
                'forumTitle' => (string) $this->settings->get('forum_title'),
            ];

            $html = $this->views->make('ernestdefoe-herald::email.html', $data)->render();
            $text = $this->views->make('ernestdefoe-herald::email.plain', $data + [
                'bodyText' => $this->plainText->fromHtml($body),
            ])->render();
        } finally {
            $this->setLocale($previous);
        }

        // Flarum's mail translator holds parameter values back as markers and
        // normally puts them in as the message is sent. Herald personalises
        // and previews BEFORE that, so it puts them back here — a preview
        // must not show "flarumsafevalue…".
        $html = SafeSubstitution::restore($html);
        $text = SafeSubstitution::restore($text, escape: false);

        $html = (new CssToInlineStyles())->convert($html, (string) file_get_contents(__DIR__.'/../../views/email/content.css'));

        return new Template($mailing->subject, $html, trim($text)."\n");
    }

    private function setLocale(string $locale): void
    {
        if ($this->translator instanceof Translator) {
            $this->translator->setLocale($locale);
        }
    }
}
