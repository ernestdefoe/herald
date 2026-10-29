<?php

namespace ErnestDefoe\Herald\Builder;

use Illuminate\Contracts\Filesystem\Factory;

/**
 * Which addresses a block may link to or show.
 *
 * Links: http, https and mailto, a path on the forum ("/d/12"), or one of the
 * URL quick tags at the start ("{suite_url}/tags"). Images: https only — the
 * server never fetches them — or a file uploaded to Herald's own folder.
 *
 * 🚨 Control characters come out FIRST. "java\tscript:" is "javascript:" to
 * a browser, and a scheme check that ran before the strip would pass it.
 */
class Url
{
    private const URL_TAGS = ['suite_url', 'settings_url', 'unsubscribe_url', 'board_url'];

    private ?string $assets = null;

    public function __construct(private Factory $filesystem)
    {
    }

    public function link(mixed $value): string
    {
        $url = $this->clean($value);

        if ($url === '') {
            return '';
        }

        if (preg_match('/^\{([a-z_]+)\}/', $url, $tag)) {
            return in_array($tag[1], self::URL_TAGS, true) ? $url : '';
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true) ? $url : '';
    }

    public function image(mixed $value): string
    {
        $url = $this->clean($value);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, $this->assets())) {
            return $url;
        }

        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' && parse_url($url, PHP_URL_HOST) ? $url : '';
    }

    /** Where uploaded images live, ending in a slash. */
    public function assets(): string
    {
        return $this->assets ??= rtrim($this->filesystem->disk('flarum-assets')->url('herald'), '/').'/';
    }

    private function clean(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $url = trim((string) preg_replace('/[\x00-\x20\x7F]+/u', '', $value));

        return mb_strlen($url) > 2000 ? '' : $url;
    }
}
