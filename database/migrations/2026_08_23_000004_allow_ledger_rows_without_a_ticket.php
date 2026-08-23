<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the Discord ledger hold a row that belongs to a DAY rather than a ticket.
 *
 * Tickets are now grouped into one forum post per business date: the forum
 * channel holds a post like «2026-08-23 · نظام التذاكر», and every ticket
 * announced that day gets its card inside it. That daily post is itself
 * something we create once, track, and later edit — exactly the shape the
 * ledger already handles — but it is not about any single ticket, so ticket_id
 * has to be allowed to be empty.
 *
 * Reusing the ledger rather than adding a table is the point: the daily post
 * then inherits the atomic claim, the deterministic nonce, the recovery scan,
 * the unverified state and the discord queue without any of it being written a
 * second time. Its uniqueness comes from the dedupe key it already has —
 * `daily-post:2026-08-23` — which is what stops three tickets activating at
 * once from opening three posts for the same day.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The FK has to come off before the column can be relaxed, then go back
        // on with the same behaviour it had.
        Schema::table('ticket_discord_messages', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
        });

        Schema::table('ticket_discord_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('ticket_id')->nullable()->change();
        });

        Schema::table('ticket_discord_messages', function (Blueprint $table) {
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_discord_messages', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
        });

        Schema::table('ticket_discord_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('ticket_id')->nullable(false)->change();
        });

        Schema::table('ticket_discord_messages', function (Blueprint $table) {
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });
    }
};
