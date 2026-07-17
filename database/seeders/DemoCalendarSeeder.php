<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Database\Seeder;

/**
 * Development only. Holidays and leave that actually collide with scheduled
 * work — otherwise the capacity meter has nothing to prove (F14).
 */
class DemoCalendarSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@etolv.test')->first();

        $holidays = [
            // Recurring: stored once, matched by month-day every year.
            ['date' => '2026-01-01', 'name' => 'رأس السنة', 'is_recurring' => true],
            ['date' => '2026-01-07', 'name' => 'عيد الميلاد المجيد', 'is_recurring' => true],
            ['date' => '2026-04-25', 'name' => 'تحرير سيناء', 'is_recurring' => true],
            ['date' => '2026-07-23', 'name' => 'ثورة يوليو', 'is_recurring' => true],
            ['date' => '2026-10-06', 'name' => 'نصر أكتوبر', 'is_recurring' => true],
            // One-off: lunar dates move, so they can't recur by month-day.
            ['date' => '2026-03-20', 'name' => 'عيد الفطر', 'is_recurring' => false],
            ['date' => '2026-05-27', 'name' => 'عيد الأضحى', 'is_recurring' => false],
        ];

        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(['date' => $holiday['date']], $holiday);
        }

        // Leave that lands on days with scheduled subtasks, so the meter has to
        // show zero capacity against real work.
        $leaves = [
            ['backend@etolv.test', now()->addDays(2), now()->addDays(4), 'annual', 'إجازة سنوية'],
            ['frontend@etolv.test', now()->addDays(9), now()->addDays(10), 'sick', 'إجازة مرضية'],
            ['fullstack@etolv.test', now()->subDays(3), now()->subDays(2), 'annual', null],
        ];

        foreach ($leaves as [$email, $from, $to, $type, $note]) {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                continue;
            }

            UserLeave::updateOrCreate(
                ['user_id' => $user->id, 'start_date' => $from->toDateString()],
                [
                    'end_date' => $to->toDateString(),
                    'type' => $type,
                    'note' => $note,
                    'approved_by' => $admin->id,
                ]
            );
        }
    }
}
