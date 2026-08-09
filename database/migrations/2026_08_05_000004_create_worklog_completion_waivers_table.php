<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-05) "X does not have to press «خلصت» — but only on the tickets he
 * shares with Y."
 *
 * A ticket cannot close while an assigned work-logging role still has an open
 * work log. That default is right and stays. The exception it needed was not a
 * blanket one: a tester who is a bottleneck on one colleague's tickets is not
 * necessarily a bottleneck on everyone's, and exempting him everywhere would
 * quietly stop his «خلصت» meaning anything at all.
 *
 * So a waiver is a PAIR. One row = "user_id is not required to finish, when
 * counterpart_user_id is working the same ticket".
 *
 * counterpart_user_id NULL is the deliberate wildcard: "with everyone". It is a
 * nullable column rather than a separate boolean because it keeps one shape —
 * the reader asks "is there a NULL row, or a row naming somebody on this
 * ticket" and never has to reconcile two competing sources.
 *
 * The relationship is ONE-WAY on purpose. "Ahmed is waived with Mahmoud" says
 * nothing about Mahmoud, who is a different person with a different job and
 * possibly the very reason the deadline exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worklog_completion_waivers', function (Blueprint $table) {
            $table->id();

            // The person being let off.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Who they are let off WITH. Null = everyone.
            $table->foreignId('counterpart_user_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            // MySQL treats NULLs as distinct in a unique index, so this stops
            // duplicate named pairs but cannot stop two "everyone" rows. The
            // application inserts that one with updateOrCreate, and a duplicate
            // would be harmless anyway — the reader only asks whether one exists.
            $table->unique(['user_id', 'counterpart_user_id'], 'waiver_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worklog_completion_waivers');
    }
};
