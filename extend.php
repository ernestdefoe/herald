<?php

/*
 * Herald — bulk email for Flarum, the way Invision Community does it.
 */

use ErnestDefoe\Herald\Api;
use ErnestDefoe\Herald\Console\ProcessCommand;
use ErnestDefoe\Herald\HeraldServiceProvider;
use ErnestDefoe\Herald\Http\ConfirmController;
use ErnestDefoe\Herald\Http\UnsubscribeController;
use ErnestDefoe\Herald\Settings;
use Flarum\Api\Context;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        /** The staff pages are lazy chunks; Flarum only publishes them from a declared directory. */
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->route('/herald', 'herald')
        ->route('/herald/new', 'herald.new')
        ->route('/herald/{id:\d+}', 'herald.edit'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\View())
        ->namespace('ernestdefoe-herald', __DIR__.'/views'),

    (new Extend\ServiceProvider())
        ->register(HeraldServiceProvider::class),

    (new Extend\Routes('api'))
        ->get('/herald/meta', 'herald.meta', Api\Meta::class)
        ->post('/herald/count', 'herald.count', Api\CountRecipients::class)
        ->post('/herald/preview', 'herald.preview', Api\PreviewMailing::class)
        ->get('/herald/mailings', 'herald.mailings.index', Api\ListMailings::class)
        ->post('/herald/mailings', 'herald.mailings.create', Api\CreateMailing::class)
        ->get('/herald/mailings/{id}', 'herald.mailings.show', Api\ShowMailing::class)
        ->patch('/herald/mailings/{id}', 'herald.mailings.update', Api\UpdateMailing::class)
        ->delete('/herald/mailings/{id}', 'herald.mailings.delete', Api\DeleteMailing::class)
        ->post('/herald/mailings/{id}/copy', 'herald.mailings.copy', Api\CopyMailing::class)
        ->post('/herald/mailings/{id}/test', 'herald.mailings.test', Api\TestMailing::class)
        ->post('/herald/mailings/{id}/send', 'herald.mailings.send', Api\SendMailing::class)
        ->post('/herald/mailings/{id}/process', 'herald.mailings.process', Api\ProcessBatch::class)
        ->post('/herald/mailings/{id}/cancel', 'herald.mailings.cancel', Api\CancelMailing::class)
        ->post('/herald/mailings/{id}/schedule', 'herald.mailings.schedule', Api\ScheduleMailing::class)
        ->delete('/herald/mailings/{id}/schedule', 'herald.mailings.unschedule', Api\ScheduleMailing::class)
        ->post('/herald/mailings/{id}/resend-failed', 'herald.mailings.resend_failed', Api\ResendFailed::class)
        ->get('/herald/mailings/{id}/deliveries', 'herald.mailings.deliveries', Api\ListDeliveries::class)
        ->post('/herald/images', 'herald.images', Api\UploadImage::class)
        ->get('/herald/subscribers', 'herald.subscribers.index', Api\ListSubscribers::class)
        ->get('/herald/subscribers/export', 'herald.subscribers.export', Api\ExportSubscribers::class)
        ->delete('/herald/subscribers/{id}', 'herald.subscribers.delete', Api\DeleteSubscriber::class)
        // The signup widget's guest form. Members use their own switch.
        ->post('/herald/subscribe', 'herald.subscribe', Api\Subscribe::class),

    (new Extend\Routes('forum'))
        ->get('/herald/unsubscribe/{user:\d+}/{token}', 'herald.unsubscribe', UnsubscribeController::class)
        ->post('/herald/unsubscribe/{user:\d+}/{token}', 'herald.unsubscribe.post', UnsubscribeController::class)
        ->get('/herald/unsubscribe/s/{subscriber:\d+}/{token}', 'herald.unsubscribe.guest', UnsubscribeController::class)
        ->post('/herald/unsubscribe/s/{subscriber:\d+}/{token}', 'herald.unsubscribe.guest.post', UnsubscribeController::class)
        ->get('/herald/confirm/{subscriber:\d+}/{token}', 'herald.confirm', ConfirmController::class)
        ->post('/herald/confirm/{subscriber:\d+}/{token}', 'herald.confirm.post', ConfirmController::class),

    // The signed token in the address is the proof on these, and a mail
    // client's one-click request carries no session.
    (new Extend\Csrf())
        ->exemptRoute('herald.unsubscribe.post')
        ->exemptRoute('herald.unsubscribe.guest.post')
        ->exemptRoute('herald.confirm.post'),

    (new Extend\Console())
        ->command(ProcessCommand::class)
        ->schedule(ProcessCommand::class, function ($event) {
            $event->everyMinute()->withoutOverlapping();
        }),

    (new Extend\Settings())
        ->default(Settings::BATCH_SIZE, Settings::DEFAULT_BATCH_SIZE)
        ->default(Settings::BATCH_DELAY, Settings::DEFAULT_BATCH_DELAY)
        ->default(Settings::GUEST_SIGNUP, true),

    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canSendHeraldMail')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission(Settings::PERMISSION)),
            // The signup widget: only the copy an admin has changed (the rest
            // are translations), and whether guests may sign up. A few bytes.
            Schema\Arr::make('heraldSignup')
                ->get(function () {
                    $settings = resolve(SettingsRepositoryInterface::class);

                    return array_filter([
                        'title' => trim((string) $settings->get(Settings::WIDGET_TITLE)),
                        'text' => trim((string) $settings->get(Settings::WIDGET_TEXT)),
                        'button' => trim((string) $settings->get(Settings::WIDGET_BUTTON)),
                        'guests' => $settings->get(Settings::GUEST_SIGNUP) !== '0',
                    ]);
                }),
        ]),

    // The member's own switch, on their Settings page. Only they can see or
    // change it — an admin turning it back on for somebody would be exactly
    // the consent problem it exists to prevent.
    (new Extend\ApiResource(Resource\UserResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('heraldSubscribed')
                ->visible(fn (User $user, Context $context) => $context->getActor()->id === $user->id)
                ->writable(fn (User $user, Context $context) => $context->getActor()->id === $user->id)
                ->get(fn (User $user) => (bool) $user->getAttribute('herald_subscribed'))
                ->set(fn (User $user, $value) => $user->setAttribute('herald_subscribed', (bool) $value)),
        ]),
];
