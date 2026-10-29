<?php

namespace ErnestDefoe\Herald\Mail;

use ErnestDefoe\Herald\Builder\BlockRenderer;
use ErnestDefoe\Herald\Builder\Discussions;
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
        private BlockRenderer $blocks,
        private Discussions $discussions,
    ) {
    }

    /**
     * @param bool $guest a newsletter subscriber's copy, whose footer says why THEY get it
     * @param bool $preview a builder preview marks blocks that will be left out
     */
    public function template(Mailing $mailing, string $locale, bool $guest = false, bool $preview = false): Template
    {
        $key = $mailing->id.'|'.$mailing->updated_at?->getTimestamp().'|'.$locale.'|'.(int) $guest.'|'.(int) $preview;

        return $this->cache[$key] ??= $this->render($mailing, $locale, $guest, $preview);
    }

    private function render(Mailing $mailing, string $locale, bool $guest, bool $preview): Template
    {
        $base = $this->url->to('forum')->base();
        $accent = $this->accent();
        $lists = $this->discussions->lists($mailing);

        $previous = $this->translator->getLocale();
        $this->setLocale($locale);

        try {
            if ($mailing->isBuilt()) {
                $body = $this->blocks->html($mailing->blocks ?? [], $lists, $accent, $base, $preview);
                $bodyText = $this->blocks->text($mailing->blocks ?? [], $lists, $base);
            } else {
                $body = $this->emailHtml->prepare($mailing->renderBody(), $base);
                $bodyText = $this->plainText->fromHtml($body);

                // The quick tag is the same list for everyone, so it goes in
                // here, once, rather than with the per-member values.
                $tag = '{'.Discussions::TAG.'}';

                if (str_contains($body, $tag)) {
                    $list = $lists['tag'] ?? [];
                    $html = $this->blocks->discussionList($list, 'compact', $accent);
                    $body = str_replace(['<p>'.$tag.'</p>', $tag], [$html, $html], $body);
                    $bodyText = str_replace($tag, $this->blocks->discussionListText($list), $bodyText);
                }
            }

            $data = [
                'subject' => $mailing->subject,
                'preheader' => (string) $mailing->preheader,
                'built' => $mailing->isBuilt(),
                'guest' => $guest,
                'body' => new HtmlString($body),
                'forumTitle' => (string) $this->settings->get('forum_title'),
            ];

            $html = $this->views->make('ernestdefoe-herald::email.html', $data)->render();
            $text = $this->views->make('ernestdefoe-herald::email.plain', $data + [
                'bodyText' => $bodyText,
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

    /** The forum's brand colour, for links and buttons. */
    public function accent(): string
    {
        $colour = (string) $this->settings->get('theme_primary_color');

        return preg_match('/^#[0-9a-f]{6}$/i', $colour) ? strtolower($colour) : '#4d698e';
    }

    private function setLocale(string $locale): void
    {
        if ($this->translator instanceof Translator) {
            $this->translator->setLocale($locale);
        }
    }
}
