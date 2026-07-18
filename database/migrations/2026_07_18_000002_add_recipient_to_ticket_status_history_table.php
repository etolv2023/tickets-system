<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A manual status change can name who the ticket is now waiting on — a team
 * member or a company contact — so "استفسار"/"بلوك" reads as "waiting on
 * someone specific", not just a colour on a badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_status_history', function (Blueprint $table) {
            $table->string('recipient_type', 10)->nullable()->after('note');
            $table->foreignId('recipient_user_id')->nullable()->after('recipient_type')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_contact_id')->nullable()->after('recipient_user_id')
                ->constrained('company_contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_status_history', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipient_contact_id');
            $table->dropConstrainedForeignId('recipient_user_id');
            $table->dropColumn('recipient_type');
        });
    }
};
