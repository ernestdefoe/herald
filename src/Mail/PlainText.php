<?php

namespace ErnestDefoe\Herald\Mail;

/**
 * The plain-text half of a multipart message.
 */
class PlainText
{
    public function fromHtml(string $html): string
    {
        // Links keep their address: "the rules (https://…/d/1)".
        $html = preg_replace_callback(
            '#<a\b[^>]*\bhref=(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            function ($m) {
                $href = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = trim(strip_tags($m[3]));

                if ($text === '' || $text === $href) {
                    return $href;
                }

                return $text.' ('.$href.')';
            },
            $html
        );

        $html = preg_replace('#<(br|hr)\b[^>]*>#i', "\n", $html);
        $html = preg_replace('#<li\b[^>]*>#i', "\n- ", $html);
        $html = preg_replace('#</(p|div|h[1-6]|blockquote|pre|ul|ol|table|tr)>#i', "\n\n", $html);
        $html = preg_replace('#<(head|style|title)\b.*?</\1>#is', '', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/ *\n */", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text)."\n";
    }
}
