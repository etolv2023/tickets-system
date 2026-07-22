<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-23) Removes the ticket «النطاق» (scope) column entirely, by
 * explicit request: it only ever existed to feed the points matrix (now
 * gone) and never carried meaning of its own beyond frontend/backend
 * classification the assignment already expresses. No data migration needed —
 * nothing else reads the value, so dropping it loses only a redundant label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }

    public function down(): void
    {
        // Recreated with its original definition; existing rows get the same
        // 'undefined' default the column always carried, since the classifying
        // value is gone for good.
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('scope', ['frontend', 'backend', 'both', 'devops', 'inquiry', 'undefined'])
                ->default('undefined')->after('type');
        });

        DB::table('tickets')->update(['scope' => 'undefined']);
    }
};
