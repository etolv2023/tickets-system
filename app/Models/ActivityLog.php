<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. F23 exposes it read-only; nothing in the app updates or
 * deletes a row.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'impersonation_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ★ (2026-08-29) F29. Set only when this action was performed by somebody
     * acting as `user`. Null on the overwhelming majority of rows.
     */
    public function impersonation(): BelongsTo
    {
        return $this->belongsTo(ImpersonationSession::class, 'impersonation_id');
    }

    /** Somebody else's hands did this. */
    public function wasImpersonated(): bool
    {
        return $this->impersonation_id !== null;
    }

    /**
     * Readable names for the action codes. The code stays what is stored and
     * filtered on; this is display only, and a code missing here shows as-is.
     *
     * @var array<string, string> action code => Arabic source label
     */
    public const ACTION_LABELS = [
        'backup.download' => 'تنزيل نسخة احتياطية',
        'backup.restore' => 'استعادة نسخة احتياطية',
        'company.created' => 'إضافة شركة',
        'company.updated' => 'تعديل شركة',
        'company.deleted' => 'حذف شركة',
        'contact.created' => 'إضافة جهة اتصال',
        'contact.updated' => 'تعديل جهة اتصال',
        'contact.deleted' => 'حذف جهة اتصال',
        'export.aging' => 'تصدير أعمار التذاكر',
        'export.approvals' => 'تصدير الموافقات',
        'export.audit' => 'تصدير سجل التدقيق',
        'export.companies' => 'تصدير الشركات',
        'export.comparison' => 'تصدير مقارنة الأداء',
        'export.contacts' => 'تصدير جهات الاتصال',
        'export.deadline' => 'تصدير تقرير المواعيد',
        'export.employee' => 'تصدير ملف موظف',
        'export.money' => 'تصدير المستحقات',
        'export.my_points' => 'تصدير نقاطي',
        'export.performance' => 'تصدير أداء الموظف',
        'export.points' => 'تصدير النقاط',
        'export.points_ledger' => 'تصدير دفتر النقاط',
        'export.points_summary' => 'تصدير ملخص النقاط',
        'export.reports' => 'تصدير التقارير',
        'export.resolved_by_type' => 'تصدير المحلول حسب النوع',
        'export.search' => 'تصدير نتائج البحث',
        'export.summary' => 'تصدير ملخص التذاكر',
        'export.team_activity' => 'تصدير نشاط الفريق',
        'export.testing_queue' => 'تصدير قائمة الاختبار',
        'export.tickets' => 'تصدير التذاكر',
        'export.timesheet' => 'تصدير سجل الوقت',
        'export.users' => 'تصدير المستخدمين',
        'github.branch.deleted' => 'حذف فرع من GitHub',
        'github.identity.linked' => 'ربط حساب GitHub',
        'github.identity.unlinked' => 'فك ربط حساب GitHub',
        'github.identity.updated' => 'تعديل ربط حساب GitHub',
        'github.repository_added' => 'إضافة مستودع جديد',
        'github.repository_updated' => 'تعديل مستودع',
        'holiday.created' => 'إضافة عطلة',
        'import.confirmed' => 'تنفيذ استيراد',
        'install.admin_created' => 'إنشاء حساب المسؤول عند التثبيت',
        'leave.created' => 'إضافة إجازة',
        'link_type.created' => 'إضافة نوع ربط',
        'link_type.updated' => 'تعديل نوع ربط',
        'link_type.deleted' => 'حذف نوع ربط',
        'point_correction.created' => 'إضافة تصحيح نقاط',
        'point_correction.replaced' => 'تعديل تصحيح نقاط',
        'point_correction.cancelled' => 'إلغاء تصحيح نقاط',
        'rating.given' => 'إضافة تقييم',
        'role.created' => 'إضافة دور',
        'role.updated' => 'تعديل دور',
        'role.duplicated' => 'نسخ دور',
        'role.deleted' => 'حذف دور',
        'schedule.ran_manually' => 'تشغيل مهمة مجدولة يدويًا',
        'schedule.updated' => 'تعديل موعد مهمة مجدولة',
        'settings.point_values.updated' => 'تعديل أسعار النقاط',
        'settings.updated' => 'تعديل الإعدادات',
        'subtask_status.created' => 'إضافة حالة مهمة فرعية',
        'subtask_status.updated' => 'تعديل حالة مهمة فرعية',
        'subtask_status.deleted' => 'حذف حالة مهمة فرعية',
        'ticket.created' => 'إنشاء تذكرة',
        'ticket.updated' => 'تعديل تذكرة',
        'ticket.deleted' => 'حذف تذكرة',
        'ticket.assigned' => 'إسناد تذكرة',
        'ticket.approved' => 'الموافقة على تذكرة',
        'ticket.rejected' => 'رفض تذكرة',
        'ticket.status_changed' => 'تغيير حالة تذكرة',
        'ticket.client_notified' => 'إبلاغ العميل',
        'ticket.branch_linked' => 'ربط فرع بتذكرة',
        'ticket.portal_password_regenerated' => 'توليد كلمة مرور جديدة للبوابة',
        'ticket_type.created' => 'إضافة نوع تذكرة',
        'ticket_type.updated' => 'تعديل نوع تذكرة',
        'ticket_type.deleted' => 'حذف نوع تذكرة',
        'user.created' => 'إضافة مستخدم',
        'user.updated' => 'تعديل مستخدم',
        'user.deleted' => 'حذف مستخدم',
        'user.restored' => 'استعادة مستخدم',
        'user.password_reset' => 'إعادة تعيين كلمة مرور مستخدم',
    ];

    /**
     * Readable names for the models a log row points at, keyed by class
     * basename — the same string the subject filter sends and matches on.
     *
     * @var array<string, string>
     */
    public const SUBJECT_LABELS = [
        'Ticket' => 'تذكرة',
        'User' => 'مستخدم',
        'Role' => 'دور',
        'Company' => 'شركة',
        'CompanyContact' => 'جهة اتصال',
        'TicketTypeDefinition' => 'نوع تذكرة',
        'TicketStatusDefinition' => 'حالة تذكرة',
        'SubtaskStatusDefinition' => 'حالة مهمة فرعية',
        'LinkTypeDefinition' => 'نوع ربط',
        'PriorityDefinition' => 'أولوية',
        'Label' => 'وسم',
        'PointTransaction' => 'تصحيح نقاط',
        'Rating' => 'تقييم',
        'ScheduledTask' => 'مهمة مجدولة',
        'GithubRepository' => 'مستودع',
        'Holiday' => 'عطلة',
        'UserLeave' => 'إجازة',
        'ImportBatch' => 'عملية استيراد',
    ];

    public static function actionLabel(string $action): string
    {
        return isset(self::ACTION_LABELS[$action]) ? __(self::ACTION_LABELS[$action]) : $action;
    }

    /** @param  string  $type  a class name or its basename */
    public static function subjectLabel(string $type): string
    {
        $base = class_basename($type);

        return isset(self::SUBJECT_LABELS[$base]) ? __(self::SUBJECT_LABELS[$base]) : $base;
    }
}
