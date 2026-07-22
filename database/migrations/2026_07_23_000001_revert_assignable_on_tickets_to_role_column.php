<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-23) Reverts the previous migration's approach: whether a role
 * shows up in the ticket assignment panel is a plain attribute of the role,
 * not a permission — mixing it into the permissions checklist confused it
 * with "what can this role's members DO", which is a different question
 * (explicit request).
 *
 * Backfills from whatever a live database already has: any role currently
 * holding the `tickets.assignable_as_role` permission gets the new column
 * set to true BEFORE the permission itself is deleted, so a role an admin
 * already granted this to (e.g. a custom "Product Owner" role) doesn't lose
 * it and doesn't need re-toggling by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('assignable_on_tickets')->default(false)->after('is_system');
        });

        $permissionId = DB::table('permissions')->where('key', 'tickets.assignable_as_role')->value('id');

        if ($permissionId !== null) {
            $roleIds = DB::table('permission_role')->where('permission_id', $permissionId)->pluck('role_id');

            if ($roleIds->isNotEmpty()) {
                DB::table('roles')->whereIn('id', $roleIds)->update(['assignable_on_tickets' => true]);
            }

            // Cascades to permission_role automatically (FK cascadeOnDelete).
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('assignable_on_tickets');
        });

        DB::table('permissions')->updateOrInsert(
            ['key' => 'tickets.assignable_as_role'],
            ['group' => 'tickets', 'name_ar' => 'يظهر كخيار في توزيع التذاكر']
        );
    }
};
