<?php

namespace ErnestDefoe\Herald\Http;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\View\Factory;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Draws the standalone page behind an email link, in the forum's own brand
 * colour so it reads as the forum's and not some third party's.
 */
class Page
{
    public function __construct(
        private Factory $views,
        private UrlGenerator $url,
        private SettingsRepositoryInterface $settings,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $state the translation group under ernestdefoe-herald.unsubscribe
     * @param array{action?: string, button?: string, formAction?: string, secondary?: bool, link?: string, linkLabel?: string} $options
     */
    public function render(string $state, array $options = [], int $status = 200): ResponseInterface
    {
        $forumTitle = (string) $this->settings->get('forum_title');
        $t = fn (string $key) => $this->translator->trans('ernestdefoe-herald.unsubscribe.'.$key, ['forumTitle' => $forumTitle]);
        $accent = (string) $this->settings->get('theme_primary_color');

        return new HtmlResponse($this->views->make('ernestdefoe-herald::unsubscribe', [
            'heading' => $t($state.'_title'),
            'text' => $t($state.'_text'),
            'action' => $options['action'] ?? null,
            'button' => isset($options['button']) ? $t($options['button']) : null,
            'formAction' => $options['formAction'] ?? null,
            'secondary' => $options['secondary'] ?? false,
            'link' => $options['link'] ?? null,
            'linkLabel' => isset($options['linkLabel']) ? $t($options['linkLabel']) : null,
            'forumTitle' => $forumTitle,
            'accent' => preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $accent) ? $accent : '#4d698e',
            'forumUrl' => $this->url->to('forum')->base(),
            'translator' => $this->translator,
        ])->render(), $status);
    }

    public function settingsUrl(): string
    {
        return $this->url->to('forum')->route('settings');
    }
}
