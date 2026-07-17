<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Columns per PLAN.md § 4.1. Edited in place rather than bolted on with an
// add_columns migration, so "one table = one migration" still holds (§ 7.3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            // Drives which assignment list a user appears in — not the role
            // alone. "both" puts one developer in both lists. F00.3
            $table->enum('skills', ['frontend', 'backend', 'both', 'none'])->default('none');
            // Productive hours per day, feeds the calendar capacity meter. F14
            $table->decimal('daily_capacity_hours', 4, 2)->default(6.00);
            // Set for Excel-imported users, who never had a chosen password. F02
            $table->boolean('must_change_password')->default(false);
            $table->string('avatar_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['role_id', 'is_active']);
            $table->index(['skills', 'is_active']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
