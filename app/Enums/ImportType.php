<?php

namespace App\Enums;

/** The three sheets the system accepts. F02 */
enum ImportType: string
{
    case Companies = 'companies';
    case Contacts = 'contacts';
    case Users = 'users';

    public function label(): string
    {
        return match ($this) {
            self::Companies => __('الشركات'),
            self::Contacts => __('جهات الاتصال'),
            self::Users => __('المستخدمون'),
        };
    }

    /** Which permission may run this import. F02 */
    public function permission(): string
    {
        return match ($this) {
            self::Companies, self::Contacts => 'companies.manage',
            self::Users => 'users.manage',
        };
    }

    /** @return array<int, string> expected sheet columns, in order */
    public function columns(): array
    {
        return match ($this) {
            self::Companies => ['name', 'code', 'is_active', 'notes'],
            self::Contacts => ['company_code', 'name', 'erp_employee_id', 'email', 'phone', 'is_active'],
            self::Users => ['name', 'email', 'role_key', 'is_active'],
        };
    }

    /** The column an existing row is matched on, for upsert. F02 */
    public function matchColumn(): string
    {
        return match ($this) {
            self::Companies => 'code',
            self::Contacts => 'erp_employee_id',
            self::Users => 'email',
        };
    }

    /**
     * One sample row for the template, in the language the template is
     * downloaded in — it is example data, so it reads as the reader writes.
     *
     * @return array<int, string>
     */
    public function sampleRow(): array
    {
        $en = app()->isLocale('en');

        return match ($this) {
            self::Companies => [$en ? 'Nile Trading Co.' : 'شركة النيل للتجارة', 'NILE', '1', $en ? 'Client since 2019' : 'عميل من 2019'],
            self::Contacts => ['NILE', $en ? 'Ahmed Fathy' : 'أحمد فتحي', 'EMP-1042', 'ahmed@nile.example', '01000000000', '1'],
            self::Users => [$en ? 'Sara Magdy' : 'سارة مجدي', 'sara@etolv.net', 'frontend', '1'],
        };
    }
}
