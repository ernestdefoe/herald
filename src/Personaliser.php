<?php

namespace ErnestDefoe\Herald;

/**
 * Puts each recipient's values into a body that was rendered ONCE.
 *
 * Rendering per recipient would be simpler and wrong: render callbacks
 * (mentions, for one) query the database, so five thousand renders is five
 * thousand rounds of queries for the same HTML.
 *
 * 🚨 A tag survives the formatter in two shapes, and both have to be found:
 *
 *   text:          Hi {member_name}
 *   link address:  <a href="%7Bsuite_url%7D/u/%7Bmember_name%7D">
 *
 * The formatter's URL filter percent-encodes the braces. Replacing only the
 * first shape leaves every personalised link pointing at the literal text
 * "%7Bsuite_url%7D" — a link that works in nobody's inbox, with no error.
 */
class Personaliser
{
    /**
     * @param array<string, string> $values plain-text values
     * @param string[] $urlTags
     */
    public function html(string $html, array $values, array $urlTags): string
    {
        $replace = [];

        foreach ($values as $name => $value) {
            $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $replace['{'.$name.'}'] = $escaped;

            // Inside an address: a URL tag goes in as the URL it is, anything
            // else is a path segment and gets encoded like one.
            $inUrl = in_array($name, $urlTags, true)
                ? $escaped
                : htmlspecialchars(rawurlencode($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $replace['%7B'.$name.'%7D'] = $inUrl;
            $replace['%7b'.$name.'%7d'] = $inUrl;
        }

        return strtr($html, $replace);
    }

    /**
     * @param array<string, string> $values
     */
    public function text(string $text, array $values): string
    {
        $replace = [];

        foreach ($values as $name => $value) {
            $replace['{'.$name.'}'] = $value;
        }

        return strtr($text, $replace);
    }
}
