<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F18 rework: every award now traces to exactly one subtask (subtask_id),
 * never a ticket-level split. The old UNIQUE(ticket_id, user_id, side) — one
 * row per person per side per ticket — no longer holds once several people can
 * each finish their own subtask on the same side; UNIQUE(subtask_id) is the
 * new guard, since a subtask can only ever be paid once.
 *
 * ticket_id becomes nullable because a manual correction (type = correction)
 * is not always about one ticket. type distinguishes the two kinds of row;
 * created_by names who entered a manual correction (null for automatic awards
 * — nobody "entered" those).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Order matters: MySQL refuses to drop an index while a foreign key
        // still relies on it, and this composite is the only index ticket_id
        // leads. The FK goes first, then the index it was leaning on.
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->dropUnique(['ticket_id', 'user_id', 'side']);
        });

        // Doctrine/DBAL isn't installed, so the nullable flip goes through raw
        // SQL rather than Blueprint::change().
        DB::statement('ALTER TABLE point_transactions MODIFY ticket_id BIGINT UNSIGNED NULL');

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->foreign('ticket_id')->references('id')->on('tickets')->restrictOnDelete();

            $table->foreignId('subtask_id')->nullable()->after('ticket_id')
                ->constrained('ticket_subtasks')->nullOnDelete();
            $table->enum('type', ['award', 'correction'])->default('award')->after('points');
            $table->foreignId('created_by')->nullable()->after('type')
                ->constrained('users')->nullOnDelete();

            $table->unique('subtask_id');
        });
    }

    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropUnique(['subtask_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['subtask_id']);
            $table->dropColumn(['subtask_id', 'type', 'created_by']);

            $table->dropForeign(['ticket_id']);
        });

        DB::statement('ALTER TABLE point_transactions MODIFY ticket_id BIGINT UNSIGNED NOT NULL');

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->foreign('ticket_id')->references('id')->on('tickets')->restrictOnDelete();
            $table->unique(['ticket_id', 'user_id', 'side']);
        });
    }
};
