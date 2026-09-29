<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEY = 'github.identities.manage';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(['key' => self::KEY], [
            'group' => 'github', 'name_ar' => 'إدارة ربط حسابات GitHub بالمستخدمين',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('key', self::KEY)->value('id');
        $adminRoleId = DB::table('roles')->where('key', 'admin')->value('id');
        if ($permissionId !== null && $adminRoleId !== null) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $adminRoleId]);
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('key', self::KEY)->value('id');
        if ($id !== null) {
            DB::table('permission_user')->where('permission_id', $id)->delete();
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
