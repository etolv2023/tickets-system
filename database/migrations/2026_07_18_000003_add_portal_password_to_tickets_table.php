<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client portal (F24): a no-login, single-ticket page reachable at
 * /portal/{ticket_number}, gated by a per-ticket password. Only the hash is
 * ever stored — the plaintext is shown once, right after it's (re)generated,
 * for staff to relay to the client out of band.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('portal_password_hash')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('portal_password_hash');
        });
    }
};
