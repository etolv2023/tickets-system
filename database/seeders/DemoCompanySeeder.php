<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Development only. Realistic customers so later phases have something to open
 * tickets against without typing data by hand (CLAUDE.md § 7.7).
 */
class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            ['شركة النيل للتجارة', 'NILE', true, 'عميل من 2019 — عقد صيانة سنوي.', [
                ['أحمد فتحي', 'EMP-1042', 'ahmed.fathy@nile.example', '01001234567'],
                ['منى عبد الرحمن', 'EMP-1189', 'mona.a@nile.example', '01112223344'],
            ]],
            ['مجموعة الدلتا الصناعية', 'DELTA', true, 'أكبر عميل من حيث عدد المستخدمين.', [
                ['خالد سليم', 'D-204', 'k.selim@delta.example', '01223334455'],
                ['هبة نصر', 'D-311', 'heba.nasr@delta.example', null],
                ['وليد مرسي', 'D-877', null, '01555667788'],
            ]],
            ['الشرق للأدوية', 'SHARQ', true, null, [
                ['سمر لطفي', 'PH-77', 'samar@sharq.example', '01099887766'],
            ]],
            ['بيت الخبرة للاستشارات', 'KHIBRA', true, 'بيبعتوا تذاكر قليلة بس معقدة.', [
                ['يوسف الطيب', 'C-12', 'youssef@khibra.example', null],
            ]],
            ['شركة الأمل (عقد منتهي)', 'AMAL', false, 'العقد خلص في 2025. محتفظين بالتاريخ.', [
                ['محمد راضي', 'A-5', 'm.rady@amal.example', null],
            ]],
        ];

        foreach ($companies as [$name, $code, $active, $notes, $contacts]) {
            $company = Company::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => $active, 'notes' => $notes]
            );

            foreach ($contacts as [$contactName, $erpId, $email, $phone]) {
                $company->contacts()->updateOrCreate(
                    ['erp_employee_id' => $erpId],
                    ['name' => $contactName, 'email' => $email, 'phone' => $phone, 'is_active' => $active]
                );
            }
        }
    }
}
