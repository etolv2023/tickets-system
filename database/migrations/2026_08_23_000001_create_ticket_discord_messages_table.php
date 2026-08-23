<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every Discord message this system has tried to send about a ticket.
 *
 * It is a ledger, not a cache: the row is written first, inside the same
 * transaction as the assignment or approval that caused it, and the queued job
 * fills in what Discord answered. That ordering is what makes the integration
 * safe — a rolled-back assignment takes its unsent rows with it, and a job that
 * dies mid-flight leaves a row that says so instead of leaving nothing.
 *
 * It also answers the questions the bell cannot: which Discord message belongs
 * to which ticket, who received it, and why somebody did not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_discord_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();

            // Null on a channel message — it is addressed to the team, not a person.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Which role's assignment this concerns. Distribution is role-based
            // (2026-07-24), so "assigned to Ahmed" is meaningless without it:
            // the same ticket can hand three roles to three people at once.
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 40);

            /*
             * Set only where a message must exist at most once no matter how
             * many times the operation runs — the ticket's announcement, and the
             * initial DMs sent when a ticket is approved. Left null for ordinary
             * assignment traffic, because handing a role back to someone who held
             * it before genuinely owes them a second message. MySQL allows any
             * number of nulls in a unique index, which is what makes one column
             * serve both cases.
             */
            $table->string('dedupe_key', 120)->nullable()->unique();

            /*
             * Sent to Discord with enforce_nonce so a retry cannot create a
             * second message.
             *
             * A pure function of this row's own id (see
             * TicketDiscordMessage::nonceValue), which is what makes it
             * identical on every attempt without anybody having to store it
             * first. Written here when the message is actually sent, so the
             * ledger keeps it for audit and for the recovery scan to match on —
             * but nothing depends on it being present beforehand. Discord caps
             * the value at 25 characters.
             */
            $table->string('nonce', 25)->nullable()->unique();

            /*
             * Written BEFORE the message is sent. A DM channel has to be opened
             * first anyway, and persisting it is what lets a later recovery pass
             * know where to go looking for a message that may or may not exist.
             */
            $table->string('channel_id', 32)->nullable();
            $table->string('message_id', 32)->nullable();

            // The ticket's thread, on the announcement row only. Every later
            // update for the ticket is posted inside it.
            $table->string('thread_id', 32)->nullable();

            // The DM this one answers — Discord threads do not exist in DMs, so
            // "no longer assigned to you" refers back to the original instead.
            $table->string('reply_to_message_id', 32)->nullable();

            // What to say, frozen at the moment it became true. A reassignment
            // has to name the two people as they were then, and a snapshot also
            // makes every retry render identically.
            $table->json('payload')->nullable();

            // pending | processing | sent | failed | skipped | unverified
            $table->string('status', 12)->default('pending');

            // When a worker took it. Combined with the status this is the claim:
            // one conditional UPDATE decides who owns the row.
            $table->timestamp('claimed_at')->nullable();

            $table->string('error', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['ticket_id', 'type']);
            $table->index(['user_id', 'type']);
            $table->index(['status', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_discord_messages');
    }
};
