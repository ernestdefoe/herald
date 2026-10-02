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
        $escape = fn (string $value) => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return strtr($html, $this->map($values, $urlTags, $escape));
    }

    /**
     * The plain-text half, and the subject. Links there are written out as
     * "text (address)", so the encoded shape turns up here too.
     *
     * @param array<string, string> $values
     * @param string[] $urlTags
     */
    public function text(string $text, array $values, array $urlTags = []): string
    {
        return strtr($text, $this->map($values, $urlTags, fn (string $value) => $value));
    }

    /**
     * @return array<string, string>
     */
    private function map(array $values, array $urlTags, callable $escape): array
    {
        $replace = [];

        foreach ($values as $name => $value) {
            $value = (string) $value;

            $replace['{'.$name.'}'] = $escape($value);

            // Inside an address: a URL tag goes in as the URL it is, anything
            // else is a path segment and gets encoded like one.
            $inUrl = $escape(in_array($name, $urlTags, true) ? $value : rawurlencode($value));

            $replace['%7B'.$name.'%7D'] = $inUrl;
            $replace['%7b'.$name.'%7d'] = $inUrl;
        }

        return $replace;
    }
}
