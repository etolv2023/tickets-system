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
    echo "❌ جدول worklog_completion_waivers غير موجود.\n";
    echo "   لم يُشغَّل الترحيل (migration) على هذا الخادم. شغّل: php artisan migrate\n";

    return;
}

$ticket = Ticket::where('ticket_number', $number)->first();

if ($ticket === null) {
    echo "❌ لا توجد تذكرة بهذا الرقم.\n";

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

echo "\nأصحاب المهام الفرعية في هذه التذكرة (وعليهم يُقاس الإعفاء):\n";
echo $holders->isEmpty()
    ? "   ⚠️  لا أحد! لذلك لن يعمل أي إعفاء مرتبط بشخص محدد هنا.\n"
    : $holders->map(fn ($n, $id) => "   • {$n} (#{$id})")->implode("\n") . "\n";

// 2. Who is still blocking, and why the waiver did or did not apply.
$assignedRoleIds = $ticket->roleAssignments()->pluck('role_id')->all();

$logs = $ticket->workLogs()
    ->where('status', '!=', 'done')
    ->whereIn('role_id', $assignedRoleIds)
    ->with(['user:id,name', 'role:id,name_ar'])
    ->get();

echo "\nمن لم يضغط «إنهاء العمل» بعد:\n";

if ($logs->isEmpty()) {
    echo "   ✅ لا أحد — التذكرة غير محظورة بسبب سجل العمل.\n";
    echo "      إذا استمر الحظر فالسبب أمر آخر (مهام فرعية غير مكتملة، أو الحالة).\n";

    return;
}

$map = WorklogCompletionWaiver::map();

foreach ($logs as $log) {
    $name = $log->user?->name ?? '?';
    printf("\n   • %s — %s (حالته: %s)\n", $name, $log->roleLabel(), $log->status);

    $waiver = $map[$log->user_id] ?? null;

    if ($waiver === null) {
        echo "     ↳ ❌ ليس لديه أي إعفاء مسجّل، وهذا سبب الحظر.\n";
        continue;
    }

    if ($waiver['all']) {
        echo "     ↳ ✅ مُعفى «مع الجميع» — يُفترض ألّا يحظر.\n";
        continue;
    }

    $names = DB::table('users')->whereIn('id', $waiver['with'])->pluck('name', 'id');
    echo "     ↳ مُعفى مع: " . ($names->isEmpty() ? '(لا يوجد)' : $names->implode('، ')) . "\n";

    $matched = array_intersect($waiver['with'], $holders->keys()->all());

    echo $matched === []
        ? "     ↳ ❌ **لا أحد منهم لديه مهمة فرعية في هذه التذكرة** — لذلك ما زال الحظر قائمًا.\n"
          . "        الحل: تدي {$name} إعفاء مع حد فعلاً له صب تاسك هنا، أو تختار «مع الكل».\n"
        : "     ↳ ✅ مطابق مع: " . $names->only($matched)->implode('، ') . "\n";
}

echo "\n", str_repeat('=', 64), "\n";
echo "جميع الإعفاءات المسجّلة في النظام: ",
    WorklogCompletionWaiver::count(), " صف\n";
