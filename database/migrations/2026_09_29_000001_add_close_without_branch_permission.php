<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEY = 'tickets.close_without_branch';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => self::KEY],
            [
                'group' => 'tickets',
                'name_ar' => 'حل أو إغلاق تذكرة بدون برانش',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('key', self::KEY)->value('id');
        $adminRoleId = DB::table('roles')->where('key', 'admin')->value('id');

        if ($permissionId !== null && $adminRoleId !== null) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $adminRoleId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', self::KEY)->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('permission_user')->where('permission_id', $permissionId)->delete();
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
