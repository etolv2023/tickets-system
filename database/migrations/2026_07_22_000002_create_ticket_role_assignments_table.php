<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (ticket, role) — who holds that role on this ticket, for every
 * role beyond the four with dedicated columns (frontend/backend/tester/devops).
 * A new custom role never needs a migration to become assignable: flipping
 * Role::assignable_on_tickets is enough, not a new column here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_role_assignments');
    }
};
