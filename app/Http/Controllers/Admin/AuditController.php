<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F23 — /admin/audit. Read-only, by design: there is no route that edits or
 * deletes a log entry, because an audit trail somebody can rewrite is not one.
 */
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('audit.view'), 403);

        $filters = $request->only('user', 'action', 'subject', 'from', 'to');

        return view('admin.audit.index', [
            'logs' => ActivityLog::query()
                ->with('user:id,name,avatar_path,is_active')
                ->when($filters['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
                ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', "{$v}%"))
                ->when($filters['subject'] ?? null, fn ($q, $v) => $q->where('subject_type', 'like', "%{$v}"))
                ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
            'filters' => $filters,
            'users' => User::without('role')->orderBy('name')->get(['id', 'name']),
            // Built from what's actually been logged, so the filter can't offer
            // an action nobody ever performed.
            'actions' => ActivityLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'subjects' => ActivityLog::query()
                ->select('subject_type')
                ->whereNotNull('subject_type')
                ->distinct()
                ->pluck('subject_type')
                ->mapWithKeys(fn ($t) => [$t => class_basename($t)]),
        ]);
    }
}
