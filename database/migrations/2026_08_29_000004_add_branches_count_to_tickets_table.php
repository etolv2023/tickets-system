<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F27. "Does this ticket have code behind it?" as a column.
 *
 * CLAUDE.md § 4.6 — the audit screen asks this of every resolved ticket, and
 * the ticket list renders the answer on 25 rows at a time. As a subquery it is
 * a correlated EXISTS on a growing table, once per row, on a screen with a
 * 300ms budget over 5,000 tickets. As a counter it is a WHERE on an indexed
 * integer, written only by the sync and the manual link.
 *
 * It counts EVERY ticket_branches row, including state='deleted'. The question
 * being answered is "was there ever a branch", not "is there one right now" —
 * a merged-and-removed branch is proof the work happened, not the absence of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedSmallInteger('branches_count')->default(0)->after('spent_hours');
            $table->index('branches_count');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['branches_count']);
            $table->dropColumn('branches_count');
        });
    }
};
