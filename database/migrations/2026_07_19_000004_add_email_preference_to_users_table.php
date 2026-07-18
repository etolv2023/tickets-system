<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-person email switch.
 *
 * The system-wide setting says whether email is on at all; this says whether
 * this particular person wants it. Someone who lives in the app all day should
 * be able to stop the mail without switching it off for the colleague who does
 * not — and without that, the first person annoyed by it asks an admin to
 * disable it globally, which silences everyone.
 *
 * Defaults to true: opting in for everyone matches what the global switch
 * already implies, and anyone who disagrees turns their own off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_notifications')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_notifications');
        });
    }
};
