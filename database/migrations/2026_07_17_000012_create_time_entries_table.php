<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// F09. Logged time has NO effect on points — that separation is deliberate, so
// nobody inflates hours to earn more (PLAN.md § 5).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // Time can be logged against the ticket directly, or a specific subtask.
            $table->foreignId('subtask_id')->nullable()->constrained('ticket_subtasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('hours', 5, 2);
            $table->date('spent_on');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'spent_on']);
            $table->index('ticket_id');
            $table->index('subtask_id');
        });

        // The Form Request enforces this too; the CHECK is what holds if a row
        // is ever written from a console or a future importer. F09
        DB::statement('ALTER TABLE time_entries ADD CONSTRAINT chk_hours CHECK (hours >= 0.25 AND hours <= 24)');
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
