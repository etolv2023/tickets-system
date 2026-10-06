<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-10-06) The date-type filter and the new reports range over
 * reported_at, which had no index (CLAUDE.md § 4.5). closed_at is not added
 * here: 2026_10_05_000001_add_filter_indexes_to_tickets_table already owns
 * tickets_closed_at_index, and a second one would collide on the name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['reported_at']);
        });
    }
};
