<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Guests who signed up for the newsletter from the widget. Members never have
 * a row here: their consent is the one switch on their Settings page.
 *
 * No IP address is stored. The confirmation token is kept only as a SHA-256
 * hash, so a copy of this table cannot confirm anybody.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('herald_subscribers')) {
            return;
        }

        $schema->create('herald_subscribers', function (Blueprint $table) {
            $table->increments('id');
            // Lower-cased on the way in, so one address is one row.
            $table->string('email', 150)->unique();
            $table->string('token_hash', 64)->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('unsubscribed_at')->nullable();
            $table->string('locale', 20)->nullable();
            $table->timestamps();

            $table->index(['confirmed_at', 'unsubscribed_at']);
            $table->index('created_at');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('herald_subscribers');
    },
];
