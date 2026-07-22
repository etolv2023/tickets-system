<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-24) The `skills` column (frontend/backend/both/none) is dropped.
 *
 * It existed to decide which assignment list a user appeared in — but
 * distribution is fully role-based now, so the user's role is their function
 * and skills does nothing. Its only readers (User::scopeFrontendCapable/
 * BackendCapable, UserSkill::coversFrontend/Backend) are already gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['skills', 'is_active']);
            $table->dropColumn('skills');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('skills', ['frontend', 'backend', 'both', 'none'])->default('none')->after('role_id');
            $table->index(['skills', 'is_active']);
        });
    }
};
