<?php

namespace App\Exports;

use App\Enums\ImportType;
use App\Enums\UserSkill;
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
        return ['العمود', 'إجباري؟', 'الشرح'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => $this->sanitizeRow($row), $this->rows());
    }

    private function rows(): array
    {
        $common = [
            ['is_active', 'اختياري', 'اكتب 1 للمفعّل و 0 للموقوف. لو سيبتها فاضية هتتحسب مفعّل.'],
        ];

        return match ($this->type) {
            ImportType::Companies => array_merge([
                ['name', 'إجباري', 'اسم الشركة كامل.'],
                ['code', 'إجباري', 'كود مختصر بحروف إنجليزية وأرقام و - و _ بس. لازم يكون فريد — وده الي شيت جهات الاتصال بيربط بيه.'],
                ['notes', 'اختياري', 'أي ملاحظات داخلية.'],
            ], $common),

            ImportType::Contacts => array_merge([
                ['company_code', 'إجباري', 'كود الشركة زي ما هو في شيت الشركات. لازم الشركة تكون مستوردة قبل كده.'],
                ['name', 'إجباري', 'اسم الموظف عند العميل.'],
                ['erp_employee_id', 'إجباري', 'رقم الموظف في الـ ERP بتاع العميل. مينفعش يتكرر داخل نفس الشركة.'],
                ['email', 'اختياري', 'إيميل صحيح أو سيبها فاضية.'],
                ['phone', 'اختياري', 'رقم تليفون.'],
            ], $common),

            ImportType::Users => array_merge([
                ['name', 'إجباري', 'اسم الموظف.'],
                ['email', 'إجباري', 'إيميل صحيح. لو موجود قبل كده الصف هيحدّث المستخدم مش هيعمل واحد جديد.'],
                ['role_key', 'إجباري', 'كود الدور. المتاح: ' . implode('، ', Role::pluck('key')->all())],
                ['skills', 'اختياري', 'المتاح: ' . implode('، ', array_column(UserSkill::cases(), 'value')) . '. بيحدد المستخدم يظهر في أنهي قائمة توزيع.'],
            ], $common, [
                ['', '', ''],
                ['ملاحظة أمنية', '', 'مفيش عمود كلمة سر — وده مقصود. كل مستخدم جديد بياخد كلمة سر عشوائية وبيتجبر يغيّرها أول دخول.'],
            ]),
        };
    }

    public function title(): string
    {
        return 'التعليمات';
    }
}
