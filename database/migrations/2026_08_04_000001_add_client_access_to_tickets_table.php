<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How to get to the problem: the login the client's system is opened with, and
 * the page it happens on.
 *
 * Whoever picks up a ticket has to reproduce it before they can fix it, and
 * until now both of those lived in the description as prose — or nowhere, and
 * were asked for in a comment a day later.
 *
 * client_user_code is a number, not a name: these are ERP account codes. It is
 * stored as a string all the same, because a code is an identifier and not a
 * quantity — leading zeros are part of it, and nothing ever does arithmetic on
 * it. No password field, by design; that is not stored here.
 *
 * Both nullable at the database level even though a client ticket requires
 * them. There are already tickets in this table that predate the fields, and a
 * NOT NULL column would either refuse the migration or invent a value for
 * every one of them. The rule lives in the Form Request, where it can also say
 * "unless this is an internal ticket".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('client_user_code', 50)->nullable()->after('module');
            // 2048: the de-facto ceiling browsers agree on for a URL.
            $table->string('page_url', 2048)->nullable()->after('client_user_code');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['client_user_code', 'page_url']);
        });
    }
};
