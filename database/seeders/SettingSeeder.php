<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Defaults for F22.2. SLA windows and daily capacity come from PLAN.md § 12
 * (#5 and #10). Every one of these is editable at /admin/settings.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'app_name', 'value' => 'نظام التذاكر', 'type' => 'string'],
            ['key' => 'app_logo', 'value' => null, 'type' => 'string'],
            // Productive hours, not clocked hours. PLAN.md § 12 #10
            ['key' => 'daily_capacity_hours', 'value' => '6', 'type' => 'float'],
            // Carbon day numbering: 0 = Sunday .. 6 = Saturday.
            ['key' => 'week_start_day', 'value' => '6', 'type' => 'int'],
            ['key' => 'work_days', 'value' => json_encode([0, 1, 2, 3, 4]), 'type' => 'json'],
            // The hours the SLA clock runs. Distinct from daily_capacity_hours:
            // that is productive planning time, these are business hours. F14
            ['key' => 'work_day_start', 'value' => '09:00', 'type' => 'string'],
            ['key' => 'work_day_end', 'value' => '17:00', 'type' => 'string'],
            ['key' => 'email_notifications_enabled', 'value' => '0', 'type' => 'bool'],
        ];

        foreach (Priority::cases() as $priority) {
            $defaults[] = [
                'key' => $priority->slaSettingKey(),
                'value' => (string) $priority->defaultSlaHours(),
                'type' => 'int',
            ];
        }

        foreach ($defaults as $setting) {
            // Only insert what's missing: never clobber a value an admin edited.
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }
    }
}
