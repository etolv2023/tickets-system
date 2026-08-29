<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-29) "…and this one was done by somebody wearing their face."
 *
 * activity_logs.user_id stays the impersonated user, deliberately: the log has
 * to agree with every other screen about who resolved a ticket. This column is
 * the footnote — null on the overwhelming majority of rows, and on the rest it
 * names the session in impersonation_sessions that produced the action.
 *
 * Indexed with created_at because the one query that reads it is "everything
 * that happened during this session, oldest first".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('impersonation_id')->nullable()->after('user_id')
                ->constrained('impersonation_sessions')->nullOnDelete();

            $table->index(['impersonation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['impersonation_id', 'created_at']);
            $table->dropForeign(['impersonation_id']);
            $table->dropColumn('impersonation_id');
        });
    }
};
