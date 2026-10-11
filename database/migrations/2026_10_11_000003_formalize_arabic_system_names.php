<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The seeded Arabic names were written in colloquial Egyptian; the product now
 * speaks plain formal Arabic. Fresh installs get the new names from the
 * seeding migrations themselves; this brings existing databases along.
 *
 * A row is only touched while it still holds the old default, so a name an
 * admin has already rewritten stays exactly as they wrote it.
 */
return new class extends Migration
{
    /** @var array<string, array<string, array<string, array{0: string, 1: string}>>> table => key => column => [old, new] */
    private const BY_KEY = [
        'ticket_statuses' => [
            'new' => ['description_ar' => ['تم تسجيل التذكرة ولم تُوزّع بعد.', 'تم تسجيل التذكرة ولم تُسنَد بعد.']],
            'pending_approval' => ['description_ar' => ['تحتاج موافقة إدارية قبل بدء التوزيع والتنفيذ.', 'تحتاج إلى موافقة إدارية قبل بدء الإسناد والتنفيذ.']],
            'assigned' => ['name_ar' => ['موزعة', 'مُسندة']],
            'in_progress' => ['name_ar' => ['جاري العمل', 'قيد التنفيذ']],
            'dev_done' => ['name_ar' => ['تم التطوير', 'اكتمل التطوير']],
            'testing' => ['name_ar' => ['تحت الاختبار', 'قيد الاختبار']],
            'reopened' => ['name_ar' => ['مرتجعة', 'أُعيد فتحها']],
        ],
        'ticket_types' => [
            'bug' => ['name_ar' => ['بج', 'خلل برمجي']],
            'feature' => ['name_ar' => ['فيتشر', 'ميزة جديدة']],
            'new_module' => ['name_ar' => ['موديول جديد', 'وحدة جديدة']],
            'exception' => ['name_ar' => ['اكسبشن', 'استثناء']],
        ],
        'subtask_statuses' => [
            'todo' => ['name_ar' => ['مستنية', 'قيد الانتظار']],
            'in_progress' => ['name_ar' => ['جاري العمل', 'قيد التنفيذ']],
            'done' => ['name_ar' => ['خلصت', 'مكتملة']],
        ],
        'link_types' => [
            'blocks' => ['name_ar' => ['بتبلوك', 'يعطّل'], 'inverse_label_ar' => ['مبلوكة بـ', 'معطَّلة بسبب']],
            'duplicates' => ['name_ar' => ['مكررة لـ', 'مكرَّرة من'], 'inverse_label_ar' => ['متكررة في', 'مكرَّرة في']],
            'caused_by' => ['name_ar' => ['سببها', 'ناتجة عن']],
        ],
        'roles' => [
            'manager' => ['name_ar' => ['مدير التيم', 'مدير الفريق']],
            'frontend' => ['name_ar' => ['مبرمج فرونت', 'مطوّر الواجهات الأمامية']],
            'backend' => ['name_ar' => ['مبرمج باك', 'مطوّر الأنظمة الخلفية']],
            'devops' => ['name_ar' => ['ديف أوبس', 'عمليات التطوير']],
            'tester' => ['name_ar' => ['تيستر', 'المختبِر']],
        ],
    ];

    /** Permissions are never renamed by admins, so they match on the old text alone. */
    private const PERMISSIONS = [
        'يظهر كخيار في توزيع التذاكر' => 'يظهر كخيار عند إسناد التذاكر',
        'عرض التذاكر المسندة إليه' => 'عرض التذاكر المُسندة إليه',
        'عرض التذاكر التي فتحها' => 'عرض التذاكر التي أنشأها',
        'سجل الشغل — بدأت وخلصت' => 'سجل العمل — بدء العمل وإنهاؤه',
        'إدارة الصب تاسكس' => 'إدارة المهام الفرعية',
        'التحكم في صب تاسكس الآخرين' => 'التحكم في المهام الفرعية للآخرين',
        'نقل الصب تاسك لشخص تاني أو حذفها' => 'نقل المهمة الفرعية إلى شخص آخر أو حذفها',
        'تحديد تاريخ استحقاق الصب تاسك' => 'تحديد تاريخ استحقاق المهمة الفرعية',
        'الموافقة على الفيتشرات والموديولات' => 'الموافقة على الميزات والوحدات الجديدة',
        'إعطاء تقييم' => 'إضافة تقييم',
        'عرض نقاطه هو' => 'عرض نقاطه الشخصية',
        'تعديل نقاط الصب تاسك والتصحيحات' => 'تعديل نقاط المهام الفرعية والتصحيحات',
        'عرض برانشات التذكرة' => 'عرض فروع التذكرة',
        'تذاكر من غير برانش وربط برانش بإيد' => 'التذاكر بلا فرع وربط الفروع يدويًا',
        'حذف برانش مخالف من GitHub' => 'حذف فرع مخالف من GitHub',
        'تشغيل استيراد Excel' => 'تنفيذ الاستيراد من Excel',
        'باك أب واسترجاع النظام كامل' => 'النسخ الاحتياطي واستعادة النظام بالكامل',
        'الدخول بعين مستخدم تاني' => 'العمل نيابةً عن مستخدم آخر',
        'مش ملزم يضغط «خلصت» لإقفال التذكرة' => 'غير مُلزَم بضغط «إنهاء العمل» لإغلاق التذكرة',
    ];

    public function up(): void
    {
        $this->apply(fn (array $pair) => $pair);
    }

    public function down(): void
    {
        $this->apply(fn (array $pair) => [$pair[1], $pair[0]]);
    }

    private function apply(callable $direction): void
    {
        foreach (self::BY_KEY as $table => $rows) {
            foreach ($rows as $key => $columns) {
                foreach ($columns as $column => $pair) {
                    [$from, $to] = $direction($pair);

                    DB::table($table)->where('key', $key)->where($column, $from)->update([$column => $to]);
                }
            }
        }

        foreach (self::PERMISSIONS as $old => $new) {
            [$from, $to] = $direction([$old, $new]);

            DB::table('permissions')->where('name_ar', $from)->update(['name_ar' => $to]);
        }

        // Cached forever as whole models; the deploy runs no cache:clear.
        foreach ([
            'ticket_types.map', 'priorities.map', 'subtask_statuses.map', 'link_types.map',
            'ticket_statuses.map', 'labels.picker', 'roles.assignable_on_tickets', 'roles.by_id',
        ] as $key) {
            Cache::forget($key);
        }
    }
};
