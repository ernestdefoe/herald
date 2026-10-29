<?php

namespace ErnestDefoe\Herald\Tests\integration\api;

use ErnestDefoe\Herald\Tests\integration\SeedsHerald;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFile;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Builder mailings: what the browser sends is cut down to the allow-list
 * before it is stored, and the renderer makes email HTML of what is left.
 */
class BuilderTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsHerald;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-herald');
        $this->seedHerald();
    }

    private function block(string $type, array $data = [], array $style = []): array
    {
        return ['id' => $type.'-1', 'type' => $type, 'data' => $data, 'style' => $style];
    }

    private function create(array $blocks, int $status = 200): array
    {
        $response = $this->call('POST', '/api/herald/mailings', 1, ['subject' => 'Built', 'type' => 'builder', 'blocks' => $blocks]);
        $this->assertSame($status, $response->getStatusCode(), (string) $response->getBody());

        return $this->body($response);
    }

    #[Test]
    public function an_unknown_block_type_is_refused()
    {
        $this->create([$this->block('html', ['html' => '<script>alert(1)</script>'])], 422);
        $this->create([['type' => 'text', 'data' => []], 'not a block'], 422);
    }

    #[Test]
    public function only_allow_listed_fields_and_addresses_are_stored()
    {
        $data = $this->create([
            $this->block('button', ['label' => str_repeat('x', 100), 'url' => "java\tscript:alert(1)", 'onclick' => 'evil()', 'color' => 'red;background:url(x)']),
            ['id' => 'img', 'type' => 'image', 'data' => ['src' => 'data:image/png;base64,AAAA', 'alt' => 'A', 'width' => 400], 'style' => ['padding' => 999, 'background' => '#ABCDEF', 'hideOnMobile' => 1, 'position' => 'fixed']],
            ['id' => 'img2', 'type' => 'image', 'data' => ['src' => 'http://insecure.example/a.png']],
            ['id' => 'link', 'type' => 'button', 'data' => ['label' => 'Go', 'url' => '{suite_url}/t/news']],
            ['id' => 'tagged', 'type' => 'button', 'data' => ['label' => 'Go', 'url' => '{member_name}']],
        ])['data'];

        [$button, $image, $insecure, $tagLink, $badTag] = $data['blocks'];

        $this->assertSame(60, mb_strlen($button['data']['label']));
        $this->assertSame('', $button['data']['url'], 'Control characters are stripped before the scheme is checked');
        $this->assertArrayNotHasKey('onclick', $button['data']);
        $this->assertSame('', $button['data']['color']);

        $this->assertSame('', $image['data']['src'], 'No data: images');
        $this->assertSame(100, $image['data']['width']);
        $this->assertSame(64, $image['style']['padding']);
        $this->assertSame('#abcdef', $image['style']['background']);
        $this->assertTrue($image['style']['hideOnMobile']);
        $this->assertArrayNotHasKey('position', $image['style']);

        $this->assertSame('', $insecure['data']['src'], 'Images must be https');
        $this->assertSame('{suite_url}/t/news', $tagLink['data']['url'], 'A URL quick tag may start a link');
        $this->assertSame('', $badTag['data']['url']);
    }

    #[Test]
    public function the_preview_is_the_real_email_with_text_escaped()
    {
        $response = $this->call('POST', '/api/herald/preview', 1, [
            'subject' => 'Hi {member_name}',
            'type' => 'builder',
            'preheader' => 'Inside: news',
            'blocks' => [
                $this->block('hero', ['title' => 'Big <b>news</b>', 'text' => 'Hello **{member_name}**, see [the rules](/d/1) and [bad](javascript:alert(1))', 'label' => 'Visit', 'url' => '{suite_url}']),
                $this->block('text', ['text' => "<img src=x onerror=alert(1)>\n\n*quiet*"]),
                $this->block('social', ['items' => [['network' => 'github', 'url' => 'https://github.com/flarum'], ['network' => 'myspace', 'url' => 'https://myspace.com']]]),
            ],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = $this->body($response);
        $html = $body['html'];

        $this->assertSame('Hi admin', $body['subject']);
        $this->assertStringContainsString('Big &lt;b&gt;news&lt;/b&gt;', $html);
        $this->assertStringContainsString('<strong>admin</strong>', $html, 'Quick tags work in builder text');
        $this->assertStringContainsString('href="http://localhost/d/1"', $html, 'Forum paths become absolute');
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('<em>quiet</em>', $html);
        $this->assertStringContainsString('max-width:600px', $html);
        $this->assertStringContainsString('Inside: news', $html, 'The preheader is in the email');
        $this->assertStringContainsString('https://github.com/flarum', $html);
        $this->assertStringNotContainsString('myspace', $html);
        $this->assertStringContainsString('/herald/unsubscribe/1/', $html, 'The unsubscribe footer is always there');

        $this->assertStringContainsString('the rules (http://localhost/d/1)', $body['text'], 'A plain-text part, with its links written out');
        $this->assertStringContainsString('Visit: http://localhost', $body['text']);
    }

    #[Test]
    public function a_builder_mailing_sends_like_any_other()
    {
        $id = $this->create([$this->block('text', ['text' => 'Hello {member_name}'])])['data']['id'];

        $this->assertSame(200, $this->call('POST', "/api/herald/mailings/$id/send", 1, [])->getStatusCode());
        $this->call('POST', "/api/herald/mailings/$id/process", 1, []);
        $this->call('POST', "/api/herald/mailings/$id/process", 1, []);

        $mailing = $this->database()->table('herald_mailings')->find($id);
        $this->assertSame('sent', $mailing->status);
        $this->assertSame(3, (int) $mailing->sent_count);
        $this->assertSame(3, $this->database()->table('herald_deliveries')->where('mailing_id', $id)->where('status', 'sent')->count());
    }

    #[Test]
    public function an_empty_builder_mailing_cannot_be_sent()
    {
        $id = $this->create([])['data']['id'];

        $this->assertSame(422, $this->call('POST', "/api/herald/mailings/$id/send", 1, [])->getStatusCode());
    }

    private function upload(string $bytes, string $name, string $type): object
    {
        $file = new UploadedFile((new StreamFactory())->createStream($bytes), strlen($bytes), UPLOAD_ERR_OK, $name, $type);

        return $this->send($this->request('POST', '/api/herald/images', ['authenticatedAs' => 1])->withUploadedFiles(['image' => $file]));
    }

    #[Test]
    public function an_uploaded_image_is_sniffed_and_re_encoded()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="10" height="10"/></svg>';
        $this->assertSame(422, $this->upload($svg, 'logo.png', 'image/png')->getStatusCode(), 'The bytes decide, not the name');

        $image = imagecreatetruecolor(2000, 1000);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();

        $response = $this->upload($jpeg."<?php echo 'hidden'; ?>", 'photo.php', 'image/jpeg');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $body = $this->body($response);
        $this->assertMatchesRegularExpression('#/assets/herald/[0-9a-f]{32}\.jpg$#', $body['url']);
        $this->assertSame(1200, $body['width'], 'Scaled down to the email width');

        $this->assertSame(403, $this->send($this->request('POST', '/api/herald/images', ['authenticatedAs' => 2]))->getStatusCode());
    }
}
