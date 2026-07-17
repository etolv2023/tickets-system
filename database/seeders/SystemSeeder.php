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
            // F00.1 lists the points matrix as part of a real install — it has
            // to be there before the first ticket is ever resolved.
            PointRuleSeeder::class,
        ]);
    }
}
