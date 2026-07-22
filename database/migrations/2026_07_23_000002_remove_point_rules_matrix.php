<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-23) Removes the admin-editable points matrix entirely, by
 * explicit request: a subtask always defaults to a flat point value
 * (SubtaskService::DEFAULT_POINTS) and is edited by hand from then on — the
 * matrix's "look up a default from (type, scope/role, side)" step goes away,
 * not the per-subtask points themselves.
 *
 * Nothing here touches the actual points already paid: point_transactions.points
 * and ticket_subtasks.points are untouched — only the `rule_id` breadcrumb
 * pointing at the (now deleted) rule that supplied a default, which was never
 * more than provenance for a number that's already stored plainly.
 *
 * FK order: both tables referencing point_rules are freed of that column
 * first, so the table itself can then be dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_subtasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rule_id');
        });

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rule_id');
        });

        Schema::dropIfExists('point_rules');
    }

    public function down(): void
    {
        // The matrix's shape as it last existed, role_id extension included —
        // recreated empty; PointRuleSeeder is gone, so a rolled-back install
        // starts this table from zero rows rather than reseeding the old F18
        // matrix defaults.
        Schema::create('point_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('ticket_type', ['bug', 'inquiry', 'feature', 'new_module', 'undefined']);
            $table->enum('scope', ['frontend', 'backend', 'both', 'any', 'devops']);
            $table->enum('side', ['support', 'frontend', 'backend', 'tester', 'devops'])->nullable();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->decimal('points', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ticket_type', 'scope', 'side']);
            $table->unique(['ticket_type', 'role_id']);
        });

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->foreignId('rule_id')->nullable()->after('type')
                ->constrained('point_rules')->nullOnDelete();
        });

        Schema::table('ticket_subtasks', function (Blueprint $table) {
            $table->foreignId('rule_id')->nullable()->after('points')
                ->constrained('point_rules')->nullOnDelete();
        });
    }
};
