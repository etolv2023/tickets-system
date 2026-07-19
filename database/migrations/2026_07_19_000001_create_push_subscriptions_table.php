<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F20.2 — one row per browser a person has allowed notifications on.
 *
 * A subscription belongs to a browser profile, not to a person: the same user
 * on a laptop and a phone is two rows, and one shared machine used by two
 * people is two rows against the same endpoint. That is why the unique key is
 * the endpoint alone — the push service issues it, and it is the address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Up to ~500 chars in practice; the column is text because no spec
            // caps it, and a truncated endpoint is a silently dead subscription.
            $table->text('endpoint');
            // Hashed because a TEXT column can't carry a plain unique index, and
            // re-subscribing the same browser must update rather than duplicate.
            $table->char('endpoint_hash', 64)->unique();

            // Kept even though the payload-less ping does not encrypt anything:
            // switching to encrypted payloads later needs exactly these two, and
            // re-collecting them would mean asking every user to re-subscribe.
            $table->string('p256dh', 255)->nullable();
            $table->string('auth', 255)->nullable();

            // So a person can tell their own devices apart when revoking one.
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
