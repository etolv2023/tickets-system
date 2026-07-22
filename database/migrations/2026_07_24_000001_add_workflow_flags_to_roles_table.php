<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-24) Phase 1 of making ticket distribution fully role-based.
 *
 * The behaviours that used to be hardcoded onto the four fixed assignment
 * columns (assigned_frontend_id/backend_id → the بدأت/خلصت work log; tester_id
 * → the testing queue) become plain flags on the role, so the admin controls
 * them from /admin/roles the same way as "تظهر في التوزيع":
 *
 *   - logs_work  : an assignment of this role gets a بدأت/خلصت work log (F07)
 *   - is_tester  : a ticket assigned to this role shows in the testing queue (F16)
 *
 * The four built-in roles are seeded to match their old behaviour exactly, and
 * are marked assignable_on_tickets so they still appear in the (now role-based)
 * distribution panel. Additive only — nothing is dropped here; the columns and
 * every code path that reads them keep working until a later phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('logs_work')->default(false)->after('assignable_on_tickets');
            $table->boolean('is_tester')->default(false)->after('logs_work');
        });

        // Match the old fixed-column behaviour, and make the four assignable so
        // the role-based panel shows them.
        DB::table('roles')->whereIn('key', ['frontend', 'backend'])
            ->update(['logs_work' => true, 'assignable_on_tickets' => true]);

        DB::table('roles')->where('key', 'tester')
            ->update(['is_tester' => true, 'assignable_on_tickets' => true]);

        DB::table('roles')->where('key', 'devops')
            ->update(['assignable_on_tickets' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['logs_work', 'is_tester']);
        });
    }
};
