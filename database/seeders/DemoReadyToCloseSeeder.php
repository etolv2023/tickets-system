<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Services\SubtaskService;
use Illuminate\Database\Seeder;

/**
 * ★ (2026-08-29) F30 demo — tickets whose every step is finished and which
 * nobody closed.
 *
 * The state this screen exists to surface does not occur in the other demo
 * seeders: they leave a ticket either mid-flight or resolved, never finished-
 * but-open. So /ready-to-close shipped showing an empty table on a fresh
 * install, which reads as "the feature does not work" rather than "there is
 * nothing to see".
 *
 * Marks the subtasks done and then lets SubtaskService::syncCounters() derive
 * subtasks_done from them, rather than writing the counter directly. A seeder
 * that sets a counter by hand can produce a row the application could never
 * produce, and then the screen is tested against a state that cannot happen.
 *
 * Never reaches production — DatabaseSeeder gates it.
 */
class DemoReadyToCloseSeeder extends Seeder
{
    /** Small enough to read on a card, big enough to be a real ticket. */
    private const MIN_SUBTASKS = 2;

    private const MAX_SUBTASKS = 5;

    public function run(SubtaskService $subtasks): void
    {
        $tickets = Ticket::query()
            ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
            ->whereBetween('subtasks_total', [self::MIN_SUBTASKS, self::MAX_SUBTASKS])
            ->whereColumn('subtasks_done', '<', 'subtasks_total')
            ->orderBy('id')
            ->limit(3)
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->subtasks()->where('status', '!=', 'done')->update([
                'status' => 'done',
                'completed_at' => now()->subHours(6),
            ]);

            // The counters come from the rows, never the other way round.
            $subtasks->syncCounters($ticket->fresh());
        }

        $ready = Ticket::whereNotIn('status', ['resolved', 'closed', 'rejected'])
            ->where('subtasks_total', '>', 0)
            ->whereColumn('subtasks_done', '>=', 'subtasks_total')
            ->count();

        $this->command?->info('  جاهزة للقفل: ' . $ready . ' تذكرة كل صب تاسكاتها خلصت وهي لسه مفتوحة.');
    }
}
