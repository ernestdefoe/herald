<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('herald_mailings')) {
            return;
        }

        $schema->create('herald_mailings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('subject', 255);
            // Formatter XML, exactly like posts.content, so any editor that
            // can write a post can write a mailing.
            $table->mediumText('content')->nullable();
            $table->text('filters')->nullable();

            // draft | sending | sent | cancelled
            $table->string('status', 20)->default('draft');

            $table->unsignedInteger('recipient_total')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            // 🚨 A cursor on the user id, never an offset: a member who joins
            // mid-send shifts every offset after them, which mails somebody
            // twice and skips somebody else.
            $table->unsignedInteger('cursor')->default(0);
            // The highest user id when Send was pressed. Members who join
            // after that are not part of this mailing.
            $table->unsignedInteger('max_user_id')->default(0);

            // One batch at a time, whoever is driving it (the admin's open
            // progress page, the queue, or the scheduler).
            $table->dateTime('locked_until')->nullable();
            $table->dateTime('last_batch_at')->nullable();

            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('sent_by')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('herald_mailings');
    },
];
