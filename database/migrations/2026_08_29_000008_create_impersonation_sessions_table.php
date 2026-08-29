<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-29) One stretch of somebody wearing somebody else's face.
 *
 * Impersonation here is FULL — by explicit request, an administrator acting as
 * another user can do everything that user can do, and every action is recorded
 * under THEIR name, because that is what makes the screens honest: a work log
 * finished by «مبرمج باك» has to read as finished by مبرمج باك.
 *
 * Which leaves one question the rest of the system can no longer answer: who
 * was actually at the keyboard. That is this table. A row per session, and
 * activity_logs.impersonation_id points every action taken during it back here.
 * So «مين دخل بعين مين وعمل إيه» is one join, and nothing else had to be
 * falsified to get it.
 *
 * Append-only like activity_logs, and for the same reason: a record of borrowed
 * identity that the borrower can delete is not a record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->id();

            // Who borrowed the face, and whose. restrictOnDelete on both: the
            // row must outlive an account being tidied away, and
            // UserDeletionService soft-deletes rather than removing anyway.
            $table->foreignId('impersonator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('impersonated_id')->constrained('users')->restrictOnDelete();

            $table->timestamp('started_at');
            // Null while it is still running. A session that never ends is a
            // browser that was closed — visible as "لسه مفتوحة" rather than
            // quietly cleaned up into looking finished.
            $table->timestamp('ended_at')->nullable();

            /*
             * How many logged actions happened inside it. A counter rather than
             * a COUNT at read time (CLAUDE.md § 4.6): the list screen shows it
             * on every row, and the number that matters is "did they DO
             * anything", which you want to see without opening each session.
             */
            $table->unsignedInteger('actions_count')->default(0);

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['impersonator_id', 'started_at']);
            $table->index(['impersonated_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');
    }
};
