<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ★ (2026-08-05) "This person is not required to press «خلصت» before the ticket
 * can be closed."
 *
 * A ticket cannot reach «تم الحل» while any assigned work-logging role still
 * has an open work log (TicketWorkflowService::unfinishedSideBlocker). That is
 * the right default and it is staying, but it makes one person's habit able to
 * hold a finished ticket open indefinitely — a tester who never gets round to
 * confirming, on a ticket everyone else has finished.
 *
 * Deliberately a permission and not a column on `roles`: the request was for a
 * NAMED PERSON, not a job title. `permission_user` already does exactly that,
 * with a `granted` flag that cuts both ways — so this can be given to one
 * tester without touching the others, and it can equally be revoked from one
 * person if it ever is granted to a role. Same shape as `subtasks.manage.any`
 * and `system.backup`, which are also granted to no role by design.
 *
 * Inserted here rather than left to PermissionSeeder alone. The seeder is the
 * catalogue, but it only runs on a fresh install or a deliberate re-seed; an
 * environment that is already live gets its permission rows from migrations, or
 * it never sees them at all. `subtasks.reassign` and `subtasks.manage.any` were
 * added to the seeder without a migration and are missing on exactly those
 * installs — worth knowing separately from this change.
 */
return new class extends Migration
{
    private const KEY = 'worklog.completion.optional';

    public function up(): void
    {
        // updateOrInsert, not insert: a fresh install has already run the
        // seeder by the time migrations finish, and this must not collide with
        // the row it wrote.
        DB::table('permissions')->updateOrInsert(
            ['key' => self::KEY],
            [
                'group' => 'workflow',
                'name_ar' => 'مش ملزم يضغط «خلصت» لإقفال التذكرة',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('key', self::KEY)->value('id');

        if ($id === null) {
            return;
        }

        // The grants first — permission_user has a foreign key, and dropping
        // the permission out from under it would fail or orphan rows.
        DB::table('permission_user')->where('permission_id', $id)->delete();
        DB::table('permission_role')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }
};
