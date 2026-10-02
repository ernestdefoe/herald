<?php

/*
 * Herald — bulk email for Flarum, the way Invision Community does it.
 */

use ErnestDefoe\Herald\Api;
use ErnestDefoe\Herald\Console\ProcessCommand;
use ErnestDefoe\Herald\HeraldServiceProvider;
use ErnestDefoe\Herald\Http\UnsubscribeController;
use ErnestDefoe\Herald\Settings;
use Flarum\Api\Context;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/herald', 'herald')
        ->route('/herald/new', 'herald.new')
        ->route('/herald/{id:\d+}', 'herald.edit'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

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
        ->post('/herald/mailings/{id}/cancel', 'herald.mailings.cancel', Api\CancelMailing::class),

    (new Extend\Routes('forum'))
        ->get('/herald/unsubscribe/{user:\d+}/{token}', 'herald.unsubscribe', UnsubscribeController::class)
        ->post('/herald/unsubscribe/{user:\d+}/{token}', 'herald.unsubscribe.post', UnsubscribeController::class),

    (new Extend\Csrf())
        ->exemptRoute('herald.unsubscribe.post'),

    (new Extend\Console())
        ->command(ProcessCommand::class)
        ->schedule(ProcessCommand::class, fn ($event) => $event->everyMinute()->withoutOverlapping()),

    (new Extend\Settings())
        ->default(Settings::BATCH_SIZE, Settings::DEFAULT_BATCH_SIZE)
        ->default(Settings::BATCH_DELAY, Settings::DEFAULT_BATCH_DELAY),

    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canSendHeraldMail')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission(Settings::PERMISSION)),
        ]),

    // The member's own switch, on their Settings page. Only they can see or
    // change it — an admin turning it back on for somebody would be exactly
    // the consent problem it exists to prevent.
    (new Extend\ApiResource(Resource\UserResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('heraldSubscribed')
                ->visible(fn (User $user, Context $context) => $context->getActor()->id === $user->id)
                ->writable(fn (User $user, Context $context) => $context->getActor()->id === $user->id)
                ->get(fn (User $user) => (bool) $user->herald_subscribed)
                ->set(fn (User $user, $value) => $user->herald_subscribed = (bool) $value),
        ]),
];
