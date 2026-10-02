<?php

namespace ErnestDefoe\Herald\Tests;

use ErnestDefoe\Herald\Personaliser;
use PHPUnit\Framework\TestCase;

class PersonaliserTest extends TestCase
{
    private const VALUES = [
        'member_name' => 'Ana <b>& "Co"',
        'member_username' => 'ana bell',
        'suite_url' => 'https://forum.example/community',
        'unsubscribe_url' => 'https://forum.example/herald/unsubscribe/7/abc',
    ];

    private const URL_TAGS = ['suite_url', 'unsubscribe_url'];

    public function test_text_tags_are_filled_and_escaped(): void
    {
        $html = (new Personaliser())->html('<p>Hi {member_name}</p>', self::VALUES, self::URL_TAGS);

        $this->assertSame('<p>Hi Ana &lt;b&gt;&amp; &quot;Co&quot;</p>', $html);
    }

    /**
     * The formatter's URL filter percent-encodes the braces of a tag written in
     * a link's address. Missing this shape leaves every personalised link
     * pointing at the literal text "%7Bsuite_url%7D".
     */
    public function test_tags_inside_a_link_address_are_filled(): void
    {
        $html = (new Personaliser())->html('<a href="%7Bsuite_url%7D/u/%7Bmember_username%7D">x</a>', self::VALUES, self::URL_TAGS);

        $this->assertSame('<a href="https://forum.example/community/u/ana%20bell">x</a>', $html);
    }

    public function test_lowercase_percent_encoding_is_filled_too(): void
    {
        $html = (new Personaliser())->html('<a href="%7bunsubscribe_url%7d">x</a>', self::VALUES, self::URL_TAGS);

        $this->assertSame('<a href="https://forum.example/herald/unsubscribe/7/abc">x</a>', $html);
    }

    public function test_a_value_cannot_inject_another_tag(): void
    {
        // strtr replaces in one pass, so a member called "{suite_url}" stays
        // that, rather than becoming the forum's address.
        $html = (new Personaliser())->html('{member_name}', ['member_name' => '{suite_url}', 'suite_url' => 'X'], []);

        $this->assertSame('{suite_url}', $html);
    }

    public function test_plain_text_is_not_html_escaped(): void
    {
        $text = (new Personaliser())->text('Hi {member_name} (%7Bsuite_url%7D)', self::VALUES, self::URL_TAGS);

        $this->assertSame('Hi Ana <b>& "Co" (https://forum.example/community)', $text);
    }

    public function test_unknown_tags_are_left_alone(): void
    {
        $this->assertSame('{not_a_tag}', (new Personaliser())->html('{not_a_tag}', self::VALUES, self::URL_TAGS));
    }
}
