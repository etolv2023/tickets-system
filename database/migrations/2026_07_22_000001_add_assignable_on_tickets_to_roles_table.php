<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin opt a role into the ticket assignment panel (F06 extension).
 * frontend/backend/tester/devops keep their own dedicated columns and stay out
 * of this — this flag is for every other role (support, manager, admin, and
 * any custom role created later at /admin/roles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('assignable_on_tickets')->default(false)->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('assignable_on_tickets');
        });
    }
};
