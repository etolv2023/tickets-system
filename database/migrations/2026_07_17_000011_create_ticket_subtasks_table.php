<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F08 — the planning layer.
 *
 * Not a duplicate of ticket_work_logs: a work log is a DECISION (this side is
 * committed, and it moves the ticket's status and the points). A subtask is
 * PLANNING (this is how the work splits, with dates and an estimate) and feeds
 * the calendar. Optional by design — forcing them produces "do the task" rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // Plain text, not the editor: a subtask is a line of work, not a essay.
            $table->text('description')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('side', ['frontend', 'backend', 'qa', 'support', 'other'])->default('other');
            $table->enum('status', ['todo', 'in_progress', 'done', 'blocked'])->default('todo');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('estimated_hours', 6, 2)->nullable();
            // Rollup from time_entries, never SUM() at render time. § 4.6
            $table->decimal('spent_hours', 6, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // Required when status = blocked. F08
            $table->string('blocked_reason')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ticket_id', 'position']);
            $table->index(['assignee_id', 'due_date']);
            $table->index('due_date');
            $table->index('status');
            // The F07 gate asks "any unfinished subtask on this side?" on every
            // finish attempt; this is the index that answers it.
            $table->index(['ticket_id', 'side', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_subtasks');
    }
};
