<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * What a real installation needs and nothing more. The installer runs exactly
 * this at step 3 (F00.1) — no demo data ever reaches a customer's database.
 */
class SystemSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingSeeder::class,
            // F27 — the four repositories are the same on every installation,
            // so they ship rather than being typed in. Nothing happens with
            // them until a token is configured.
            GithubRepositorySeeder::class,
        ]);
    }
}
