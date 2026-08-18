<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-19) A deadline with an hour on it.
 *
 * `due_date` is a DATE, and F18.1 reads it as "the end of that day" — which is
 * the right reading for planned work, where a subtask is due «النهاردة» and
 * finishing it at 4pm or 7pm is the same thing.
 *
 * It is the wrong reading for an exception. An error raised at 10am with a
 * four-working-hour deadline is late at 2pm, not at midnight, and a DATE cannot
 * say that — every exception raised on a working day would be "on time" until
 * the day ended, which turns the four hours into a decoration.
 *
 * So: a nullable DATETIME that means "due at this exact moment". `due_date`
 * stays exactly what it was, keeps every screen and filter it already feeds,
 * and stays the answer for the subtasks nobody sets an hour on — which is
 * almost all of them. TicketSubtask::finishedLate() prefers `due_at` when it
 * is set and falls back to `due_date` otherwise, so the rule is one method
 * with two inputs rather than two rules that can disagree.
 *
 * Indexed alongside the assignee for the same reason `due_date` is: the
 * penalty sweep asks "whose is overdue" every hour, and that question is only
 * cheap with an index behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_subtasks', function (Blueprint $table) {
            $table->dateTime('due_at')->nullable()->after('due_date');
            $table->index('due_at');
            $table->index(['assignee_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_subtasks', function (Blueprint $table) {
            $table->dropIndex(['assignee_id', 'due_at']);
            $table->dropIndex(['due_at']);
            $table->dropColumn('due_at');
        });
    }
};
