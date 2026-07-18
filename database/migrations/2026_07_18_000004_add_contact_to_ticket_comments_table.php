<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A portal comment is written by the client, not a system user — user_id
 * becomes nullable and contact_id names who from the company actually wrote
 * it. Exactly one of the two is ever set; TicketComment::authorName() reads
 * whichever is there.
 */
return new class extends Migration
{
    public function up(): void
    {
        // doctrine/dbal isn't installed (CLAUDE.md § 1), so Blueprint::change()
        // isn't available — raw MODIFY instead. The existing FK stays: MySQL
        // only enforces a FK's referential integrity on non-null values.
        DB::statement('ALTER TABLE ticket_comments MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('user_id')
                ->constrained('company_contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
        });

        DB::statement('ALTER TABLE ticket_comments MODIFY user_id BIGINT UNSIGNED NOT NULL');
    }
};
