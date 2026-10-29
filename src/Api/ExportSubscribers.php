<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Settings;
use ErnestDefoe\Herald\Subscriber;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Every guest subscriber as CSV, read in chunks so a long list never sits in
 * memory whole.
 */
class ExportSubscribers implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $actor->assertCan(Settings::PERMISSION);

        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['email', 'status', 'subscribed_at', 'confirmed_at', 'unsubscribed_at', 'locale'], escape: '');

        Subscriber::query()->orderBy('id')->chunk(500, function ($subscribers) use ($out) {
            foreach ($subscribers as $s) {
                // A leading = + - @ would be a formula to a spreadsheet.
                $email = preg_match('/^[=+\-@]/', $s->email) ? "'".$s->email : $s->email;
                fputcsv($out, [$email, ListSubscribers::status($s), $s->created_at?->toIso8601String(), $s->confirmed_at?->toIso8601String(), $s->unsubscribed_at?->toIso8601String(), $s->locale], escape: '');
            }
        });

        rewind($out);

        return (new Response('php://memory', 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="newsletter-subscribers.csv"',
            'Cache-Control' => 'no-store',
        ]))->withBody(new \Laminas\Diactoros\Stream($out));
    }
}
