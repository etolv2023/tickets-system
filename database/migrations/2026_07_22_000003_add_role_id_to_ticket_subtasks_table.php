<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a subtask be tied to an assignable role (support/manager/admin/custom)
 * instead of the fixed `side` enum, so the same "Done subtask -> points" path
 * that pays frontend/backend pays these roles too — no separate award
 * mechanism, no touch to the existing side columns or their enum casts.
 * Null for every existing subtask; `side` stays exactly as it was ('other' by
 * default) and keeps meaning what it always meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_subtasks', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('side')
                ->constrained('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_subtasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
