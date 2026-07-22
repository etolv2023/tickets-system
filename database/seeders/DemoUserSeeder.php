<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Development only — never called by the installer. Covers every role plus the
 * edge cases worth clicking through: a locked-out
 * user, and one forced to change their password (CLAUDE.md § 7.7).
 */
class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'etolv@2026';

    public function run(): void
    {
        $roleIds = Role::pluck('id', 'key');

        $users = [
            ['محمود كامل', 'admin@etolv.test', 'admin', []],
            ['هدى شعراوي', 'manager@etolv.test', 'manager', []],
            ['كريم عبد الله', 'support@etolv.test', 'support', []],
            ['نورهان سامي', 'frontend@etolv.test', 'frontend', []],
            ['طارق الشناوي', 'backend@etolv.test', 'backend', []],
            // A second frontend, so the frontend assignment list has more than one name.
            ['ياسمين فؤاد', 'fullstack@etolv.test', 'frontend', []],
            ['أحمد منصور', 'tester@etolv.test', 'tester', []],
            ['طارق سليم', 'devops@etolv.test', 'devops', []],
            // is_active = false must be refused at login with a clear message. F00.2
            ['سلمى راضي', 'inactive@etolv.test', 'support', ['is_active' => false]],
            // Mimics an Excel-imported user: blocked until they set a password. F02
            ['عمر الديب', 'imported@etolv.test', 'backend', ['must_change_password' => true]],
        ];

        foreach ($users as [$name, $email, $roleKey, $extra]) {
            User::updateOrCreate(
                ['email' => $email],
                array_merge([
                    'name' => $name,
                    'password' => self::PASSWORD,
                    'role_id' => $roleIds[$roleKey],
                    'daily_capacity_hours' => 6.00,
                    'is_active' => true,
                    'must_change_password' => false,
                ], $extra)
            );
        }
    }
}
