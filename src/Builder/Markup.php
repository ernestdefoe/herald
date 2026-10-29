<?php

namespace ErnestDefoe\Herald\Builder;

/**
 * The builder's text: **bold**, *italic*, [links](https://…) and paragraphs,
 * nothing else.
 *
 * Text is stored as this small markup, never as HTML. The output is built
 * here from escaped text, so there is no HTML from the browser to sanitise,
 * and nothing a member of staff types can become a tag of its own.
 */
class Markup
{
    // One level of balanced brackets in the address, for links like
    // https://en.wikipedia.org/wiki/Flarum_(software).
    private const LINK = '/\[([^\]\n]{1,300})\]\(((?:[^()\s]++|\([^()\s]*+\))++)\)/u';

    public function __construct(private Url $url)
    {
    }

    /**
     * @param string $style inline style for each paragraph
     */
    public function html(string $text, string $style, string $linkColour, string $base): string
    {
        $paragraphs = preg_split("/\n\s*\n/", trim(str_replace("\r", '', $text))) ?: [];
        $out = '';

        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) === '') {
                continue;
            }

            $links = [];

            // Links out first, as placeholders, so the emphasis rules below
            // never reach inside an address.
            $line = preg_replace_callback(self::LINK, function ($m) use (&$links, $linkColour, $base) {
                $href = $this->url->link($m[2]);
                $label = $this->inline($this->escape($m[1]));
                $links[] = $href === ''
                    ? $label
                    : '<a href="'.$this->escape($this->absolute($href, $base)).'" style="color:'.$linkColour.';text-decoration:underline;">'.$label.'</a>';

                return "\x01".(count($links) - 1)."\x01";
            }, $paragraph);

            $line = nl2br($this->inline($this->escape($line)), false);
            $line = preg_replace_callback("/\x01(\d+)\x01/", fn ($m) => $links[(int) $m[1]], $line);

            $out .= '<p style="'.$style.'">'.$line.'</p>';
        }

        return $out;
    }

    public function text(string $text, string $base): string
    {
        $text = preg_replace_callback(self::LINK, function ($m) use ($base) {
            $href = $this->url->link($m[2]);

            return $href === '' ? $m[1] : $m[1].' ('.$this->absolute($href, $base).')';
        }, trim(str_replace("\r", '', $text)));

        return (string) preg_replace(['/\*\*(.+?)\*\*/s', '/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/s'], '$1', $text);
    }

    private function inline(string $html): string
    {
        $html = preg_replace('/\*\*(?!\s)(.+?)(?<!\s)\*\*/s', '<strong>$1</strong>', $html);

        return (string) preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/s', '<em>$1</em>', $html);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function absolute(string $url, string $base): string
    {
        return str_starts_with($url, '/') ? rtrim($base, '/').$url : $url;
    }
}
