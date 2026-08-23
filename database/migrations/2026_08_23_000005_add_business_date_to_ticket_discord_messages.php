<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The day a ledger row belongs to, as a column instead of a JSON lookup.
 *
 * Two places needed this and both were reading it out of the payload. One asked
 * "is a header refresh already queued for this date?" with whereJsonContains,
 * which MySQL cannot index — a full scan of a table that grows by several rows
 * per ticket, forever, executed inside the web request. The other counted a
 * day's tickets by loading EVERY announcement row and filtering in PHP.
 *
 * Neither was wrong when the table was empty. Both get slower every day the
 * system is used, which is the kind of cost that never announces itself.
 *
 * Backfilled from the payload so existing rows keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded because the column and its index are added by a DDL statement,
        // which MySQL commits on its own — so a run that dies at the backfill
        // below leaves them behind and the migration is never recorded. Without
        // this, the retry fails on "duplicate column" and the backfill never
        // gets a second chance.
        if (! Schema::hasColumn('ticket_discord_messages', 'business_date')) {
            Schema::table('ticket_discord_messages', function (Blueprint $table) {
                $table->date('business_date')->nullable()->after('type');
                // The two questions asked of it: "the header row for this date"
                // and "the announcements belonging to this date".
                $table->index(['business_date', 'type']);
            });
        }

        // JSON_UNQUOTE rather than a PHP loop: this runs once, on whatever size
        // the table already is, and there is no reason to pull it into memory.
        // JSON_TYPE = 'STRING', not "IS NOT NULL".
        //
        // A row announced while forum mode was off stored business_date as JSON
        // null, and JSON null is not SQL NULL — the IS NOT NULL test passes it
        // through, JSON_UNQUOTE turns it into the four-character string 'null',
        // and MySQL refuses that as a date. Asking for the type is the only
        // check that tells a real date from a JSON null.
        DB::statement("
            UPDATE ticket_discord_messages
            SET business_date = JSON_UNQUOTE(JSON_EXTRACT(payload, '$.business_date'))
            WHERE payload IS NOT NULL
              AND JSON_TYPE(JSON_EXTRACT(payload, '$.business_date')) = 'STRING'
        ");
    }

    public function down(): void
    {
        Schema::table('ticket_discord_messages', function (Blueprint $table) {
            $table->dropIndex(['business_date', 'type']);
            $table->dropColumn('business_date');
        });
    }
};
