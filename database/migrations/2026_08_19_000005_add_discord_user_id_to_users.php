<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-19) F26.1 — who to actually ping on Discord.
 *
 * An exception ticket already has an owner. The Discord message announcing it
 * did not name them, so the announcement reached a channel rather than a
 * person, and "somebody will see it" is how a four-hour deadline gets missed.
 *
 * This column is what makes a real mention possible. It has to be the numeric
 * Discord user id — the snowflake — and NOT the @username, because those are
 * two different things to Discord:
 *
 *   "@mahmoud"               plain text. Renders grey, pings nobody.
 *   "<@709211234567890123>"  a real mention, with a real notification.
 *
 * Usernames are also mutable and were made changeable again in 2023; ids never
 * change. Storing the handle would mean a silent, undiagnosable failure the
 * first time somebody renamed themselves — the message would still send, still
 * look almost right, and simply stop notifying.
 *
 * A string, not an integer: snowflakes are 64-bit and 17–20 digits, which
 * overflows a signed BIGINT on some paths and is never arithmetic anyway. 32
 * leaves room for the format to grow.
 *
 * Nullable, and stays nullable. Nobody is forced to hand over a Discord id to
 * be assigned work; the sender falls back to the person's name in plain text,
 * which announces the same fact without the notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_user_id', 32)->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('discord_user_id');
        });
    }
};
