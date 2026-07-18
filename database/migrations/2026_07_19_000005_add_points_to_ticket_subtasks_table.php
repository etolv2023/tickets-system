<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F18/F08: points now live on the subtask, not the ticket. SubtaskService
 * fills a default from the point_rules matrix at creation time, but it is a
 * plain editable column from then on — nobody's stuck with what the matrix
 * said the day the subtask was made.
 *
 * rule_id remembers which matrix rule supplied that default (null when the
 * side had none, or when the subtask's side is 'other'), purely so the points
 * report can say "على أساس إيه" — it plays no part in awarding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_subtasks', function (Blueprint $table) {
            $table->decimal('points', 5, 2)->default(0)->after('estimated_hours');
            $table->foreignId('rule_id')->nullable()->after('points')
                ->constrained('point_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_subtasks', function (Blueprint $table) {
            $table->dropForeign(['rule_id']);
            $table->dropColumn(['points', 'rule_id']);
        });
    }
};
