<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F18 — an immutable ledger. These rows become money in a bonus run.
 *
 * Nothing updates or deletes a row here. A correction is a NEW row with negative
 * points, exactly as an accounting ledger works: the history of what was paid
 * has to stay readable after the fact.
 *
 * The unique index is the last line of defence against double-spending. The
 * engine already checks points_awarded_at, but a guard in code can be bypassed
 * by a race; a unique index cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->enum('side', ['support', 'frontend', 'backend', 'tester']);
            $table->decimal('points', 5, 2);
            $table->foreignId('rule_id')->nullable()->constrained('point_rules')->nullOnDelete();
            // Computed at write time from resolved_at, never DATE_FORMAT() at
            // report time (§ 4.6). CHAR(7) = 'YYYY-MM'.
            $table->char('period', 7);
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['ticket_id', 'user_id', 'side']);
            $table->index(['user_id', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
