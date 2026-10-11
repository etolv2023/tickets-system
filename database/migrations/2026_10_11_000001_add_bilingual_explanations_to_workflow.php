<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->string('name_en', 100)->nullable()->after('name_ar');
            $table->string('description_ar', 255)->nullable()->after('name_en');
            $table->string('description_en', 255)->nullable()->after('description_ar');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->string('name_en', 100)->nullable()->after('name_ar');
            $table->string('description_ar', 255)->nullable()->after('name_en');
            $table->string('description_en', 255)->nullable()->after('description_ar');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('name_en', 150)->nullable()->after('name_ar');
            $table->string('description_ar', 255)->nullable()->after('name_en');
            $table->string('description_en', 255)->nullable()->after('description_ar');
        });

        $statuses = [
            'new' => ['New', 'تم تسجيل التذكرة ولم تُوزّع بعد.', 'Recorded and awaiting assignment.'],
            'pending_approval' => ['Awaiting approval', 'تحتاج موافقة إدارية قبل بدء التوزيع والتنفيذ.', 'Requires administrative approval before assignment and execution.'],
            'rejected' => ['Rejected', 'رُفض الطلب وانتهى مساره دون تنفيذ.', 'The request was rejected and will not proceed.'],
            'assigned' => ['Assigned', 'حُدّد المسؤولون، ولم يبدأ التنفيذ بعد.', 'Owners are assigned; execution has not started.'],
            'in_progress' => ['In progress', 'بدأ فريق التنفيذ العمل على التذكرة.', 'The delivery team is actively working on the ticket.'],
            'dev_done' => ['Development complete', 'انتهى التنفيذ الفني وتنتظر التذكرة الاختبار أو الاعتماد.', 'Implementation is complete and awaits testing or verification.'],
            'testing' => ['In testing', 'يُراجع فريق الاختبار النتيجة قبل اعتماد الحل.', 'QA is validating the result before resolution.'],
            'reopened' => ['Reopened', 'أُعيدت للتنفيذ بعد ظهور ملاحظة أو فشل الاختبار.', 'Returned to delivery after feedback or failed verification.'],
            'resolved' => ['Resolved', 'اعتُمد الحل وانتهى العمل؛ هنا تُحتسب النقاط مرة واحدة.', 'Work is verified; points are awarded once at this stage.'],
            'closed' => ['Closed', 'تم إبلاغ العميل وإغلاق الالتزام نهائيًا.', 'The client was notified and the commitment is fully closed.'],
        ];

        foreach ($statuses as $key => [$nameEn, $descriptionAr, $descriptionEn]) {
            DB::table('ticket_statuses')->where('key', $key)->update([
                'name_en' => $nameEn,
                'description_ar' => $descriptionAr,
                'description_en' => $descriptionEn,
            ]);
        }

        $roles = [
            'admin' => ['System administrator', 'يدير إعدادات النظام والمستخدمين والصلاحيات؛ ليس جهة تنفيذ تلقائيًا.'],
            'manager' => ['Team manager', 'يخطط العمل ويوزّعه ويتابع الأداء والاعتمادات.'],
            'support' => ['Customer support', 'يستقبل طلب العميل ويتابعه ويبلّغه بالنتيجة.'],
            'frontend' => ['Frontend developer', 'ينفذ واجهات وتجربة المستخدم الخاصة بالتذكرة.'],
            'backend' => ['Backend developer', 'ينفذ منطق الخادم والبيانات والتكاملات.'],
            'devops' => ['DevOps engineer', 'يتولى النشر والبنية التحتية والعمليات التشغيلية.'],
            'tester' => ['Quality assurance', 'يتحقق من النتيجة ويعتمد الحل أو يعيده للتنفيذ.'],
        ];

        foreach ($roles as $key => [$nameEn, $descriptionAr]) {
            DB::table('roles')->where('key', $key)->update([
                'name_en' => $nameEn,
                'description_ar' => $descriptionAr,
            ]);
        }

        DB::table('permissions')->orderBy('id')->get(['id', 'key'])->each(function ($permission) {
            $action = Str::after($permission->key, '.');
            $resource = Str::before($permission->key, '.');

            DB::table('permissions')->where('id', $permission->id)->update([
                'name_en' => Str::headline("{$action} {$resource}"),
                'description_ar' => 'صلاحية وصول مستقلة؛ منحها لا يغيّر مسؤولية المستخدم داخل التذكرة.',
                'description_en' => 'An independent access permission; granting it does not assign ticket responsibility.',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('permissions', fn (Blueprint $table) => $table->dropColumn(['name_en', 'description_ar', 'description_en']));
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn(['name_en', 'description_ar', 'description_en']));
        Schema::table('ticket_statuses', fn (Blueprint $table) => $table->dropColumn(['name_en', 'description_ar', 'description_en']));
    }
};
