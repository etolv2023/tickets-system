<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * The permission list from PLAN.md § 2. Seeded, never hardcoded in a Gate (F00.3).
 * Idempotent: the installer and migrate:fresh both land on the same rows.
 */
class PermissionSeeder extends Seeder
{
    /** @var array<string, array<string, string>> group => [key => Arabic name] */
    private const PERMISSIONS = [
        'tickets' => [
            'tickets.view.all' => 'عرض كل التذاكر',
            'tickets.view.assigned' => 'عرض التذاكر المسندة إليه',
            'tickets.view.own' => 'عرض التذاكر التي فتحها',
            'tickets.create' => 'فتح تذكرة',
            'tickets.edit' => 'تعديل تذكرة',
            'tickets.delete' => 'حذف تذكرة',
            'tickets.assign' => 'توزيع التذاكر',
            'tickets.resolve' => 'تعليم التذكرة كمحلولة',
            'tickets.reopen' => 'إعادة فتح تذكرة',
            'tickets.close' => 'إغلاق التذكرة',
            'tickets.notify_client' => 'تسجيل إبلاغ العميل',
        ],
        'comments' => [
            'comments.create' => 'إضافة تعليق',
            'comments.internal' => 'كتابة تعليق داخلي',
        ],
        'workflow' => [
            'worklog.manage' => 'سجل الشغل — بدأت وخلصت',
            'subtasks.manage' => 'إدارة الصب تاسكس',
            // ★ (2026-08-02) subtasks.manage lets you plan YOUR work. This is
            // the override that reaches somebody else's — reassigning it,
            // finishing it, deleting it. Deliberately granted to NO role: it is
            // a named person's exception (permission_user), not a job title's
            // property, exactly like system.backup below.
            'subtasks.manage.any' => 'التحكم في صب تاسكس الآخرين',
            // ★ (2026-08-02) Handing a subtask to somebody else, or deleting it,
            // split out of subtasks.manage. Editing your own step is planning;
            // moving the work off yourself — or removing the record that it
            // existed — changes who gets paid for it (F18), which is a different
            // kind of decision and now a separately revocable one.
            //
            // Seeded to every role that already held subtasks.manage, so nothing
            // anyone could do yesterday stops working. The point is that it can
            // now be taken away from ONE person without touching the rest.
            'subtasks.reassign' => 'نقل الصب تاسك لشخص تاني أو حذفها',
            // ★ (2026-08-05) The due date became a money field the day a
            // subtask finished after it started costing MINUS its points
            // (PointEngineService::isLate). A deadline the person being
            // measured can move is not a deadline, so writing it is now its own
            // permission — held by whoever plans the work (admin, manager), not
            // by everyone who does it. Reading it is untouched: the date still
            // shows on every row and drives the calendar.
            'subtasks.schedule' => 'تحديد تاريخ استحقاق الصب تاسك',
            'time.log' => 'تسجيل الوقت',
            'links.manage' => 'ربط التذاكر ببعضها',
            'features.approve' => 'الموافقة على الفيتشرات والموديولات',
        ],
        'ratings' => [
            'ratings.give' => 'إعطاء تقييم',
            'ratings.view.all' => 'عرض تقييمات الجميع',
        ],
        'points' => [
            'points.view.all' => 'عرض نقاط الجميع',
            'points.view.own' => 'عرض نقاطه هو',
            'points.rules.manage' => 'تعديل نقاط الصب تاسك والتصحيحات',
            /*
             * ★ (2026-08-29) Undoing a manual correction, split into two
             * permissions of its own rather than folded into
             * points.rules.manage above.
             *
             * Writing a correction and unwriting one are different authorities.
             * The first adds a line somebody can read and argue with; the
             * second takes back points a person may already have been told they
             * earned, and — because the reversal is posted into the ORIGINAL's
             * period — can change the total of a month whose bonus was already
             * paid. Neither is destructive to the ledger (both only insert),
             * but both move money, so both are separately revocable.
             *
             * Granted to no role by default. An admin holds them through '*';
             * anyone else is named by hand, like system.backup.
             */
            'points.corrections.edit' => 'تعديل تصحيح نقاط',
            'points.corrections.delete' => 'إلغاء تصحيح نقاط',
        ],
        'reports' => [
            'reports.view' => 'عرض التقارير',
        ],
        /*
         * ★ (2026-08-29) F27 — GitHub. Two permissions, and there is no third.
         *
         * There is deliberately no github.branch.create and no github.delete,
         * because the integration is read-only: the token behind it holds
         * Contents: Read-only, and GitHubClient has no method that issues
         * anything but GET. Nothing this system can be permitted to do would
         * change a repository.
         *
         * github.audit is the stricter of the two. It carries the screen
         * listing resolved tickets with no code behind them — which is a list
         * of people's unproven work — and the ability to attach a branch to a
         * ticket by hand. That second one is why it is not simply folded into
         * reports.view: attaching a branch is an assertion that work exists,
         * and it is checked against GitHub before it is accepted, but it is
         * still an assertion and it should belong to whoever is accountable
         * for the answer.
         */
        'github' => [
            'github.view' => 'عرض برانشات التذكرة',
            'github.audit' => 'تذاكر من غير برانش وربط برانش بإيد',
        ],
        'admin' => [
            'users.manage' => 'إدارة المستخدمين والأدوار',
            'companies.manage' => 'إدارة الشركات وجهات الاتصال',
            'settings.manage' => 'إدارة الإعدادات',
            'audit.view' => 'عرض سجل التدقيق',
            'import.run' => 'تشغيل استيراد Excel',
            // Deliberately its own permission, not folded into settings.manage:
            // restore replaces every row and every file the system holds, which
            // is a different order of risk from editing the SLA hours. Never
            // granted to a role automatically — an admin assigns it by hand.
            'system.backup' => 'باك أب واسترجاع النظام كامل',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $group => $items) {
            foreach ($items as $key => $nameAr) {
                Permission::updateOrCreate(
                    ['key' => $key],
                    ['group' => $group, 'name_ar' => $nameAr]
                );
            }
        }
    }
}
