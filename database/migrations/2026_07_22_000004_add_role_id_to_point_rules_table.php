<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the F18 matrix to cover role-based points (support/manager/admin/
 * custom roles), alongside the existing (ticket_type, scope, side) rows —
 * neither touched nor renumbered.
 *
 * `side` only needs to go nullable so a role-based row can leave it blank;
 * every existing row keeps its value, only the future constraint relaxes.
 * Doctrine/DBAL isn't installed, so the nullable flip is a raw ALTER, same
 * approach as 2026_07_19_000006's ticket_id change.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE point_rules MODIFY side ENUM('support','frontend','backend','tester','devops') NULL"
        );

        Schema::table('point_rules', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('side')
                ->constrained('roles')->nullOnDelete();

            $table->unique(['ticket_type', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::table('point_rules', function (Blueprint $table) {
            $table->dropUnique(['ticket_type', 'role_id']);
            $table->dropConstrainedForeignId('role_id');
        });

        DB::statement(
            "ALTER TABLE point_rules MODIFY side ENUM('support','frontend','backend','tester','devops') NOT NULL"
        );
    }
};
