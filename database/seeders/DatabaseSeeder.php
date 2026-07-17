<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SystemSeeder::class);

        // Demo accounts exist so a developer can log in as every role straight
        // after migrate:fresh --seed. They must never reach production.
        if (! app()->isProduction()) {
            $this->call([
                DemoUserSeeder::class,
                DemoCompanySeeder::class,
                DemoTicketSeeder::class,
                DemoJiraSeeder::class,
                DemoCalendarSeeder::class,
            ]);
        }
    }
}
