<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F07 — the row that solves "frontend and backend on the same ticket".
 *
 * One row per side, each with its own state. This is a DECISION: it drives the
 * ticket's status and the points. Subtasks (phase 4) are PLANNING and drive the
 * calendar. The two must not be confused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('side', ['frontend', 'backend']);
            $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamps();

            // One commitment per side, per ticket. F07
            $table->unique(['ticket_id', 'side']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_work_logs');
    }
};
