<?php

namespace ErnestDefoe\Herald\Tests;

use ErnestDefoe\Herald\Mail\PlainText;
use PHPUnit\Framework\TestCase;

class PlainTextTest extends TestCase
{
    public function test_links_keep_their_address(): void
    {
        $text = (new PlainText())->fromHtml('<p>Read <a href="https://f.example/d/1">the rules</a>.</p>');

        $this->assertSame("Read the rules (https://f.example/d/1).\n", $text);
    }

    public function test_a_bare_link_is_not_doubled(): void
    {
        $text = (new PlainText())->fromHtml('<a href="https://f.example">https://f.example</a>');

        $this->assertSame("https://f.example\n", $text);
    }

    public function test_paragraphs_and_lists(): void
    {
        $text = (new PlainText())->fromHtml('<p>One</p><p>Two &amp; three</p><ul><li>a</li><li>b</li></ul>');

        $this->assertSame("One\n\nTwo & three\n\n- a\n- b\n", $text);
    }

    public function test_head_and_styles_are_dropped(): void
    {
        $text = (new PlainText())->fromHtml('<html><head><title>T</title><style>p{color:red}</style></head><body><p>Hi</p></body></html>');

        $this->assertSame("Hi\n", $text);
    }
}
