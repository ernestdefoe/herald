<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * One row per recipient per mailing: what happened to their copy.
 *
 * 🚨 The row is written BEFORE the message is handed to the mail server, and
 * the unique keys mean it can be written only once. A second driver, or a
 * process restarted mid-batch, cannot insert it again, so cannot send again.
 * A row still "queued" after its process died may or may not have gone out;
 * it becomes "unknown" and is never retried automatically.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('herald_deliveries')) {
            return;
        }

        $schema->create('herald_deliveries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mailing_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('subscriber_id')->nullable();
            // queued | sent | failed | skipped | unknown
            $table->string('status', 10);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error', 255)->nullable();
            $table->dateTime('next_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['mailing_id', 'user_id']);
            $table->unique(['mailing_id', 'subscriber_id']);
            $table->index(['mailing_id', 'status']);
            $table->index(['status', 'next_attempt_at']);
            // The sending limits count what went out in the last minute, hour and day.
            $table->index('sent_at');

            $table->foreign('mailing_id')->references('id')->on('herald_mailings')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('subscriber_id')->references('id')->on('herald_subscribers')->cascadeOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('herald_deliveries');
    },
];
