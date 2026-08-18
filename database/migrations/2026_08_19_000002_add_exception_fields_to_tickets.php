<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-19) F26 — what makes an exception ticket findable again.
 *
 * The sending system groups errors by a fingerprint over the stack trace, so
 * "the same error" is a question it has already answered. Storing that answer
 * here is what lets a second report find the first report's ticket instead of
 * opening a hundred tickets for one broken query.
 *
 * NOT unique, on purpose. The rule is: an error that comes back while its
 * ticket is still open adds a comment there, and an error that comes back
 * AFTER its ticket was closed opens a new ticket linked to the closed one. The
 * second half means one fingerprint legitimately owns a chain of tickets over
 * time — a unique index would make the third recurrence a 500 instead of a
 * ticket. The index is still needed and still narrow: the lookup is always
 * "the latest ticket carrying this fingerprint".
 *
 * `exception_count` is the occurrence figure as of the LAST report, copied from
 * the sender rather than counted here. This system only hears about an error
 * when the sender chooses to tell it; counting our own calls would produce a
 * number lower than the truth and invite someone to trust it.
 *
 * `exception_server` is duplicated out of the description on purpose. Errors
 * arrive from several servers, and "which server" is the first thing anyone
 * filters or groups by — a fact that lives only inside an HTML blob is a fact
 * no query can reach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('exception_fingerprint', 64)->nullable()->after('page_url');
            $table->unsignedInteger('exception_count')->default(1)->after('exception_fingerprint');
            $table->string('exception_server', 100)->nullable()->after('exception_count');

            // The one query the intake runs: latest ticket for this fingerprint.
            $table->index(['exception_fingerprint', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['exception_fingerprint', 'id']);
            $table->dropColumn(['exception_fingerprint', 'exception_count', 'exception_server']);
        });
    }
};
