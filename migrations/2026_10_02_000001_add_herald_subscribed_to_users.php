<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Its own consent, not a notification preference. Agreeing to hear about a
 * reply is not agreeing to a mailing list.
 *
 * Default TRUE: members are opted in, the way Invision Community does it, and
 * every message says plainly how to opt out.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('users', 'herald_subscribed')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->boolean('herald_subscribed')->default(true);
        });
    },

    'down' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'herald_subscribed')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn('herald_subscribed');
        });
    },
];
