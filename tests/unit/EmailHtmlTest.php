<?php

namespace ErnestDefoe\Herald\Tests\unit;

use ErnestDefoe\Herald\Mail\EmailHtml;
use PHPUnit\Framework\TestCase;

class EmailHtmlTest extends TestCase
{
    private function prepare(string $html): string
    {
        return (new EmailHtml())->prepare($html, 'https://forum.example/');
    }

    public function test_relative_links_and_images_become_absolute(): void
    {
        $out = $this->prepare('<p><a href="/u/ana">Ana</a> <img src="/assets/x.png"> <a href="#top">t</a></p>');

        $this->assertStringContainsString('href="https://forum.example/u/ana"', $out);
        $this->assertStringContainsString('src="https://forum.example/assets/x.png"', $out);
        $this->assertStringContainsString('href="#top"', $out);
    }

    public function test_protocol_relative_urls_get_https(): void
    {
        $this->assertStringContainsString('src="https://cdn.example/a.png"', $this->prepare('<img src="//cdn.example/a.png">'));
    }

    public function test_scripts_and_handlers_are_removed(): void
    {
        $out = $this->prepare('<p onclick="x()" data-id="3">Hi</p><script>alert(1)</script><style>p{}</style>');

        $this->assertStringNotContainsString('script', $out);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('data-id', $out);
        $this->assertStringNotContainsString('<style', $out);
        $this->assertStringContainsString('Hi', $out);
    }

    public function test_an_embed_becomes_a_link(): void
    {
        $out = $this->prepare('<iframe src="https://www.youtube.com/embed/abc"></iframe>');

        $this->assertStringNotContainsString('iframe', $out);
        $this->assertStringContainsString('<a href="https://www.youtube.com/embed/abc">', $out);
    }

    public function test_tags_survive_in_both_shapes(): void
    {
        $out = $this->prepare('<p>{member_name}</p><a href="%7Bsuite_url%7D">x</a>');

        $this->assertStringContainsString('{member_name}', $out);
        $this->assertMatchesRegularExpression('/href="(%7B|\{)suite_url(%7D|\})"/', $out);
    }

    public function test_non_ascii_text_is_kept(): void
    {
        $this->assertStringContainsString('Grüße — 你好', $this->prepare('<p>Grüße — 你好</p>'));
    }

    public function test_empty_body(): void
    {
        $this->assertSame('', $this->prepare('   '));
    }
}
