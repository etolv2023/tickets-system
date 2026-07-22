<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-24) Converts subtask status from a fixed PHP enum + MySQL ENUM
 * column into an admin-manageable list — same pattern as ticket_statuses,
 * ticket_types and priorities. The admin can rename a status, recolour it, and
 * add their own — all without a code change.
 *
 * The four original statuses are seeded here (not a Seeder) as SYSTEM rows so
 * `migrate` alone works on a live database. `done`, `in_progress` and `blocked`
 * carry code behaviour (points/counters, started_at, blocked reason), so their
 * keys are fixed and the rows can't be deleted; `needs_reason` replaces the
 * hardcoded SubtaskStatus::needsReason() (blocked). A custom status an admin
 * adds is a neutral open state — never counted as done, so it never earns.
 */
return new class extends Migration
{
    /** @var array<int, array{key: string, name_ar: string, color: string, needs_reason: bool}> */
    private const SYSTEM_STATUSES = [
        ['key' => 'todo', 'name_ar' => 'مستنية', 'color' => 'slate', 'needs_reason' => false],
        ['key' => 'in_progress', 'name_ar' => 'جاري العمل', 'color' => 'cyan', 'needs_reason' => false],
        ['key' => 'done', 'name_ar' => 'خلصت', 'color' => 'green', 'needs_reason' => false],
        ['key' => 'blocked', 'name_ar' => 'متوقفة', 'color' => 'amber', 'needs_reason' => true],
    ];

    public function up(): void
    {
        Schema::create('subtask_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('name_ar', 100);
            $table->string('color', 20)->default('slate');
            // F08: a status with this flag makes the subtask require a reason
            // (blocked out of the box). Replaces SubtaskStatus::needsReason().
            $table->boolean('needs_reason')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach (self::SYSTEM_STATUSES as $position => $status) {
            DB::table('subtask_statuses')->insert($status + [
                'is_system' => true,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // The column was a strict ENUM, so every existing row's value is already
        // one of the keys just inserted — the FK can't fail on live data. Raw
        // SQL because doctrine/dbal (Blueprint::change) isn't installed.
        DB::statement("ALTER TABLE ticket_subtasks MODIFY status VARCHAR(30) NOT NULL DEFAULT 'todo'");
        DB::statement('ALTER TABLE ticket_subtasks ADD CONSTRAINT fk_subtasks_status FOREIGN KEY (status) REFERENCES subtask_statuses (`key`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ticket_subtasks DROP FOREIGN KEY fk_subtasks_status');
        DB::statement("ALTER TABLE ticket_subtasks MODIFY status ENUM('todo', 'in_progress', 'done', 'blocked') NOT NULL DEFAULT 'todo'");

        Schema::dropIfExists('subtask_statuses');
    }
};
