<?php

namespace App\Exports;

use App\Enums\ImportType;
use App\Exports\Concerns\SanitizesCells;
use App\Models\Role;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** The "what does this column mean" sheet that ships inside every template. F02 */
class ImportInstructionsSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    use SanitizesCells;

    public function __construct(private readonly ImportType $type)
    {
    }

    public function headings(): array
    {
        return [__('العمود'), __('إلزامي؟'), __('الشرح')];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => $this->sanitizeRow($row), $this->rows());
    }

    private function rows(): array
    {
        $common = [
            ['is_active', __('اختياري'), __('اكتب 1 للمفعّل و0 للموقوف. إذا تُرك فارغًا فسيُعدّ مفعّلًا.')],
        ];

        return match ($this->type) {
            ImportType::Companies => array_merge([
                ['name', __('إلزامي'), __('اسم الشركة كاملًا.')],
                ['code', __('إلزامي'), __('كود مختصر بحروف إنجليزية وأرقام و- و_ فقط. يجب أن يكون فريدًا، وبه يرتبط ملف جهات الاتصال.')],
                ['notes', __('اختياري'), __('أي ملاحظات داخلية.')],
            ], $common),

            ImportType::Contacts => array_merge([
                ['company_code', __('إلزامي'), __('كود الشركة كما هو في ملف الشركات. يجب استيراد الشركة مسبقًا.')],
                ['name', __('إلزامي'), __('اسم الموظف لدى العميل.')],
                ['erp_employee_id', __('إلزامي'), __('رقم الموظف في نظام ERP لدى العميل، ولا يجوز تكراره داخل الشركة نفسها.')],
                ['email', __('اختياري'), __('بريد إلكتروني صحيح أو اتركه فارغًا.')],
                ['phone', __('اختياري'), __('رقم الهاتف.')],
            ], $common),

            ImportType::Users => array_merge([
                ['name', __('إلزامي'), __('اسم الموظف.')],
                ['email', __('إلزامي'), __('بريد إلكتروني صحيح. إذا كان مسجلًا من قبل فسيُحدَّث المستخدم بدلًا من إنشاء مستخدم جديد.')],
                ['role_key', __('إلزامي'), __('كود الدور. المتاح: :roles', ['roles' => implode(__('، '), Role::pluck('key')->all())])],
            ], $common, [
                ['', '', ''],
                [__('ملاحظة أمنية'), '', __('لا يوجد عمود لكلمة المرور، وهذا مقصود. يحصل كل مستخدم جديد على كلمة مرور عشوائية ويُلزَم بتغييرها عند أول دخول.')],
            ]),
        };
    }

    public function title(): string
    {
        return __('التعليمات');
    }
}
