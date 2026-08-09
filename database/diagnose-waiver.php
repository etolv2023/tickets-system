<?php

/**
 * Why is this ticket refusing to close?
 *
 * Run on the server, with the ticket number:
 *
 *     php artisan tinker --execute="\$TICKET='TK-2026-00042'; require 'database/diagnose-waiver.php';"
 *
 * Prints, for that ticket: who is still holding it up, who holds a subtask on
 * it, what waivers each blocker has, and which of those waivers matched.
 * Read-only — it changes nothing.
 */

use App\Models\Ticket;
use App\Models\WorklogCompletionWaiver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$number = $TICKET ?? null;

if (blank($number)) {
    echo "لازم تحدد رقم التذكرة: \$TICKET='TK-2026-00042'\n";

    return;
}

echo str_repeat('=', 64), "\n";

// 0. Is the feature even deployed here?
if (! Schema::hasTable('worklog_completion_waivers')) {
    echo "❌ جدول worklog_completion_waivers مش موجود.\n";
    echo "   المايجريشن ماتشغلتش على السيرفر ده. شغّل: php artisan migrate\n";

    return;
}

$ticket = Ticket::where('ticket_number', $number)->first();

if ($ticket === null) {
    echo "❌ مفيش تذكرة بالرقم ده.\n";

    return;
}

printf("التذكرة   : %s — %s\n", $ticket->ticket_number, $ticket->status->value);

// 1. Who holds a subtask here? This is the set a waiver is matched against.
$holders = DB::table('ticket_subtasks')
    ->join('users', 'users.id', '=', 'ticket_subtasks.assignee_id')
    ->where('ticket_subtasks.ticket_id', $ticket->id)
    ->whereNull('ticket_subtasks.deleted_at')
    ->distinct()
    ->pluck('users.name', 'users.id');

echo "\nمين له صب تاسك على التذكرة دي (ده اللي الإعفاء بيتقاس عليه):\n";
echo $holders->isEmpty()
    ? "   ⚠️  محدش! فمفيش أي إعفاء بشخص معيّن هيشتغل هنا.\n"
    : $holders->map(fn ($n, $id) => "   • {$n} (#{$id})")->implode("\n") . "\n";

// 2. Who is still blocking, and why the waiver did or did not apply.
$assignedRoleIds = $ticket->roleAssignments()->pluck('role_id')->all();

$logs = $ticket->workLogs()
    ->where('status', '!=', 'done')
    ->whereIn('role_id', $assignedRoleIds)
    ->with(['user:id,name', 'role:id,name_ar'])
    ->get();

echo "\nاللي لسه مضغطش «خلصت»:\n";

if ($logs->isEmpty()) {
    echo "   ✅ محدش — التذكرة مش متبلوكة من سجل الشغل.\n";
    echo "      لو لسه مش بتتقفل، السبب حاجة تانية (صب تاسكس مش خالصة، أو الحالة).\n";

    return;
}

$map = WorklogCompletionWaiver::map();

foreach ($logs as $log) {
    $name = $log->user?->name ?? '?';
    printf("\n   • %s — %s (حالته: %s)\n", $name, $log->roleLabel(), $log->status);

    $waiver = $map[$log->user_id] ?? null;

    if ($waiver === null) {
        echo "     ↳ ❌ مالوش أي إعفاء متسجّل. ده سبب البلوك.\n";
        continue;
    }

    if ($waiver['all']) {
        echo "     ↳ ✅ معفي «مع الكل» — المفروض مش بيبلوك.\n";
        continue;
    }

    $names = DB::table('users')->whereIn('id', $waiver['with'])->pluck('name', 'id');
    echo "     ↳ معفي مع: " . ($names->isEmpty() ? '(مفيش)' : $names->implode('، ')) . "\n";

    $matched = array_intersect($waiver['with'], $holders->keys()->all());

    echo $matched === []
        ? "     ↳ ❌ **مفيش حد منهم له صب تاسك على التذكرة دي** — عشان كده البلوك لسه شغّال.\n"
          . "        الحل: تدي {$name} إعفاء مع حد فعلاً له صب تاسك هنا، أو تختار «مع الكل».\n"
        : "     ↳ ✅ اتطابق مع: " . $names->only($matched)->implode('، ') . "\n";
}

echo "\n", str_repeat('=', 64), "\n";
echo "كل الإعفاءات المتسجّلة في السيستم: ",
    WorklogCompletionWaiver::count(), " صف\n";
