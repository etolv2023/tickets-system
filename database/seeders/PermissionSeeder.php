<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * The 29 permissions from PLAN.md § 2. Seeded, never hardcoded in a Gate (F00.3).
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
            'points.rules.manage' => 'تعديل مصفوفة النقاط',
        ],
        'reports' => [
            'reports.view' => 'عرض التقارير',
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
