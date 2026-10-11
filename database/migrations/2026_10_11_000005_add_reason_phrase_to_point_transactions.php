<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reason a system-written ledger row gives, as a translation key and its
 * parameters, so it can be read in English too. `reason` itself is untouched —
 * it is still written in Arabic, still what search matches and exports carry —
 * and rows written before this keep a null here and show `reason` as stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->json('reason_phrase')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropColumn('reason_phrase');
        });
    }
};
