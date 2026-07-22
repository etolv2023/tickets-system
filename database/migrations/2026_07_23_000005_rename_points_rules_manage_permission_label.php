<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ★ (2026-07-23) The `points.rules.manage` permission used to gate the points
 * matrix; the matrix is gone, and the permission now controls who can edit a
 * subtask's points (TicketSubtaskPolicy::updatePoints) and enter manual
 * corrections. Its display name still read "تعديل مصفوفة النقاط", which is
 * misleading on the roles screen — this renames it in place so an admin
 * granting the permission sees what it actually does. Key is untouched; only
 * the Arabic label changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('key', 'points.rules.manage')
            ->update(['name_ar' => 'تعديل نقاط الصب تاسك والتصحيحات']);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('key', 'points.rules.manage')
            ->update(['name_ar' => 'تعديل مصفوفة النقاط']);
    }
};
