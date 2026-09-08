<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The board's «مغلقة» column is bounded by month now (F12.1, 2026-09-08), and
 * the bound reads resolved_at — a column nothing indexed.
 *
 * `status IN (...) AND resolved_at BETWEEN ...` cannot use the existing
 * (status, priority, created_at) index for the range half, so without this the
 * month window is a full scan of every resolved ticket the system has ever
 * had. § 4.5 asks for an index on every column in a WHERE; this is that.
 *
 * A single-column index rather than (status, resolved_at): the same column is
 * what /reports groups every month by (ReportService reads resolved_at with no
 * status predicate at all), so the narrower index serves both callers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['resolved_at']);
        });
    }
};
