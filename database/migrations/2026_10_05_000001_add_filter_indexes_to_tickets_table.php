<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-10-05) The list filter grew a date-basis picker (closed_at,
 * updated_at), an approval-state filter and a module search. Every column a
 * WHERE or ORDER BY can land on carries an index (CLAUDE.md § 4.5); these four
 * were the ones that did not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('closed_at');
            $table->index('updated_at');
            $table->index('approval_status');
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['closed_at']);
            $table->dropIndex(['updated_at']);
            $table->dropIndex(['approval_status']);
            $table->dropIndex(['module']);
        });
    }
};
