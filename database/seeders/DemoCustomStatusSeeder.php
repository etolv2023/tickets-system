<?php

namespace Database\Seeders;

use App\Models\TicketStatusDefinition;
use App\Models\TicketStatusTransition;
use Illuminate\Database\Seeder;

/**
 * Development only. Two admin-added custom statuses ("استفسار" / "بلوك") that
 * exercise the dynamic status engine end to end — proof that a status outside
 * the original fixed 10 actually works, ahead of the admin CRUD screen that
 * will let a real admin add these by hand.
 */
class DemoCustomStatusSeeder extends Seeder
{
    private const CUSTOM_STATUSES = [
        ['key' => 'awaiting_reply', 'name_ar' => 'استفسار', 'color' => 'amber', 'position' => 10],
        ['key' => 'blocked', 'name_ar' => 'بلوك', 'color' => 'red', 'position' => 11],
    ];

    /** Any of these open system statuses may move into either custom status, and back. */
    private const FROM_SYSTEM = ['new', 'assigned', 'in_progress', 'dev_done', 'testing'];
    private const BACK_TO = ['assigned', 'in_progress'];

    public function run(): void
    {
        $custom = collect(self::CUSTOM_STATUSES)->map(
            fn (array $row) => TicketStatusDefinition::firstOrCreate(
                ['key' => $row['key']],
                $row + ['is_system' => false, 'is_open' => true]
            )
        );

        $statuses = TicketStatusDefinition::whereIn('key', [...self::FROM_SYSTEM, ...self::BACK_TO])
            ->get()->keyBy('key');

        foreach ($custom as $status) {
            foreach (self::FROM_SYSTEM as $fromKey) {
                TicketStatusTransition::firstOrCreate([
                    'from_status_id' => $statuses[$fromKey]->id,
                    'to_status_id' => $status->id,
                ]);
            }

            foreach (self::BACK_TO as $toKey) {
                TicketStatusTransition::firstOrCreate([
                    'from_status_id' => $status->id,
                    'to_status_id' => $statuses[$toKey]->id,
                ]);
            }
        }
    }
}
