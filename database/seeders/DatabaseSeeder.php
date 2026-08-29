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
                DemoCustomStatusSeeder::class,
                DemoTicketSeeder::class,
                DemoJiraSeeder::class,
                DemoCalendarSeeder::class,
                // Five months of finished work, so the reports, the points
                // ledger and the notification inbox have a history to show
                // rather than a single week of rows.
                DemoHistorySeeder::class,
                // F27 — after the history, because it reads which tickets ended
                // up resolved and gives most (not all) of them a branch.
                DemoGithubSeeder::class,
                // F18 — the corrections screen, with a cancelled row and an
                // edited one on it. After the history so it has real tickets
                // and real people to point at.
                DemoPointCorrectionSeeder::class,
                // Last: it reads the users and companies the others created,
                // and it is the one you open to see how the pieces fit.
                DemoFeatureWalkthroughSeeder::class,
            ]);
        }
    }
}
