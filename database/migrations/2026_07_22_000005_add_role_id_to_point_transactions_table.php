<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger side of the same extension: a role-based award records which
 * role earned it here instead of a `side` value. `side` goes nullable for
 * that one new case; every existing row's side is untouched.
 *
 * No extra unique index here — a role-based award is still a subtask award,
 * so the existing UNIQUE(subtask_id) already guards it exactly like every
 * other side. A second unique on (ticket_id, role_id, user_id) would wrongly
 * block two different Done subtasks on the same role paying the same person
 * twice — which F18 explicitly allows for the fixed sides already ("نفس
 * الشخص فرونت وباك → سطرين منفصلين").
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE point_transactions MODIFY side ENUM('support','frontend','backend','tester','devops') NULL"
        );

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('side')
                ->constrained('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        DB::statement(
            "ALTER TABLE point_transactions MODIFY side ENUM('support','frontend','backend','tester','devops') NOT NULL"
        );
    }
};
