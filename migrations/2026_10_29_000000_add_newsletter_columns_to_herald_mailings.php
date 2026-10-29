<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Herald 2.0: builder mailings, templates, scheduling and repeats, and the
 * second cursor for guest subscribers. One table still holds every mailing,
 * whichever way it was written.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('herald_mailings', 'type')) {
            return;
        }

        $schema->table('herald_mailings', function (Blueprint $table) {
            // editor | builder
            $table->string('type', 10)->default('editor');
            // Builder blocks as JSON, normalised against an allow-list on save.
            $table->mediumText('blocks')->nullable();
            $table->string('preheader', 255)->nullable();
            // What the Latest discussions blocks listed when the send began,
            // so every recipient gets the same list.
            $table->mediumText('frozen')->nullable();

            $table->boolean('is_template')->default(false);
            $table->dateTime('archived_at')->nullable();

            // When it goes, in UTC, and the zone it was chosen in. A repeating
            // mailing keeps its first time as the anchor every later issue is
            // counted from, in that zone, so 9am stays 9am across DST.
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('schedule_anchor')->nullable();
            $table->string('timezone', 64)->nullable();
            // daily | weekly | monthly — not "repeat", a reserved word in MySQL.
            $table->string('repeat_every', 10)->nullable();
            // An issue of a repeating mailing points at its series.
            $table->unsignedInteger('series_id')->nullable();

            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('subscriber_cursor')->default(0);
            $table->unsignedInteger('max_subscriber_id')->default(0);

            $table->index(['status', 'scheduled_at']);
            $table->index('series_id');
            $table->index('is_template');
        });
    },

    'down' => function (Builder $schema) {
        if (! $schema->hasColumn('herald_mailings', 'type')) {
            return;
        }

        $schema->table('herald_mailings', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropIndex(['series_id']);
            $table->dropIndex(['is_template']);
            $table->dropColumn([
                'type', 'blocks', 'preheader', 'frozen', 'is_template', 'archived_at', 'scheduled_at', 'schedule_anchor',
                'timezone', 'repeat_every', 'series_id', 'skipped_count', 'subscriber_cursor', 'max_subscriber_id',
            ]);
        });
    },
];
