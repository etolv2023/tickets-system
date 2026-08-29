<?php

namespace Database\Seeders;

use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PointCorrectionService;
use Illuminate\Database\Seeder;

/**
 * ★ (2026-08-29) F18 demo — the corrections screen with something on it.
 *
 * /admin/point-rules shipped with an empty table on a fresh install, so the one
 * screen where points turn into a decision could not be looked at without
 * typing rows by hand first. Now it seeds four, and deliberately not four of
 * the same kind: one still standing, one cancelled, and one edited — which is
 * three rows on its own, because an edit writes a reversal and a replacement.
 *
 * The cancel and the edit go through PointCorrectionService rather than being
 * hand-built here, so what the screen shows is what the buttons actually
 * produce, not an imitation of it.
 *
 * Never reaches production — DatabaseSeeder gates it.
 */
class DemoPointCorrectionSeeder extends Seeder
{
    public function run(PointCorrectionService $corrections): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('key', 'admin'))->orderBy('id')->first();

        if ($admin === null) {
            return;
        }

        $period = now()->format('Y-m');
        $ticket = Ticket::orderByDesc('id')->value('ticket_number');

        // One correction per developer role that actually has somebody in it.
        $rows = [
            ['backend', 6, 'ساعد في ترحيل قاعدة البيانات خارج وقت الشغل', $ticket],
            ['frontend', -4, 'سلّم الشاشة من غير ما يراجع الريسبونسف', null],
            ['tester', 3, 'مسك باج في الإنتاج قبل ما العميل يشوفه', null],
            ['support', -2, 'قفل التذكرة من غير ما يبلّغ العميل', null],
        ];

        $created = [];

        foreach ($rows as [$roleKey, $points, $reason, $ticketNumber]) {
            $role = Role::where('key', $roleKey)->first();
            $person = User::whereHas('role', fn ($q) => $q->where('key', $roleKey))->orderBy('id')->first();

            if ($role === null || $person === null) {
                continue;
            }

            $created[$roleKey] = PointTransaction::create([
                'user_id' => $person->id,
                'role_id' => $role->id,
                'ticket_id' => $ticketNumber === null
                    ? null
                    : Ticket::where('ticket_number', $ticketNumber)->value('id'),
                'points' => $points,
                'type' => 'correction',
                'created_by' => $admin->id,
                'period' => $period,
                'reason' => $reason,
            ]);
        }

        // One cancelled outright — the «اتلغى» state.
        if (isset($created['support'])) {
            $corrections->cancel($created['support'], $admin->id, 'اتضح إن العميل اتبلّغ فعلاً');
        }

        // One edited — the «اتعدّل» state, and the three rows behind it.
        if (isset($created['frontend'])) {
            $corrections->replace($created['frontend'], [
                'user_id' => $created['frontend']->user_id,
                'role_id' => $created['frontend']->role_id,
                'points' => -2,
                'reason' => 'سلّم الشاشة من غير ما يراجع الريسبونسف — الخصم اتظبط بعد المراجعة',
                'ticket_number' => null,
            ], $admin->id);
        }

        $this->command?->info(
            '  تصحيحات النقاط: ' . PointTransaction::where('type', 'correction')->count()
            . ' سطر (منهم ' . PointTransaction::whereNotNull('reverses_id')->count() . ' سطر إلغاء).'
        );
    }
}
