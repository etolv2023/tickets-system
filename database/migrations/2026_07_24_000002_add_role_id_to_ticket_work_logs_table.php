<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-24) Phase 1 of role-based distribution.
 *
 * A work log (F07) used to be tied to a WorkSide enum (frontend/backend). Now
 * that any role can be flagged logs_work, a work log belongs to a role instead.
 * This is additive: role_id is added and backfilled from `side`, but `side`
 * stays (nullable now) so the old side-based write path keeps working until a
 * later phase rewrites it.
 *
 * doctrine/dbal is not installed, so `side` is made nullable with raw SQL rather
 * than Schema::change().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_work_logs', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
        });

        // A future role-based log has no WorkSide; the column must accept NULL.
        DB::statement("ALTER TABLE ticket_work_logs MODIFY side ENUM('frontend','backend') NULL");
    }

    public function down(): void
    {
        Schema::table('ticket_work_logs', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });

        // Only safe to restore NOT NULL because every existing row still carries
        // its `side` — nothing in this phase writes a null side.
        DB::statement("ALTER TABLE ticket_work_logs MODIFY side ENUM('frontend','backend') NOT NULL");
    }
};
