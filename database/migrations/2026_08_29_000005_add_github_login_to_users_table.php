<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F27. The GitHub handle, so a commit author becomes a person.
 *
 * Same shape and same reasoning as discord_user_id: an identifier on another
 * service, filled in by hand, allowed to be empty. Nothing breaks without it —
 * a branch just shows the raw login instead of a name.
 *
 * Unique so two accounts cannot claim the same handle, but nullable, so most
 * rows hold NULL. MySQL allows any number of NULLs in a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('github_login', 100)->nullable()->unique()->after('discord_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['github_login']);
            $table->dropColumn('github_login');
        });
    }
};
