<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestoreUploadRequest;
use App\Services\ActivityLogger;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A full-system backup/restore, gated on its own `system.backup` permission —
 * deliberately not folded into `settings.manage`: restore replaces every row
 * and every uploaded file the system currently holds, which is a different
 * order of risk from editing the SLA hours.
 */
class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly ActivityLogger $activity,
    ) {
    }

    public function index(): View
    {
        return view('admin.backup.index', [
            'confirmPhrase' => BackupService::CONFIRM_PHRASE,
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $path = $this->backups->build();

        $this->activity->log(
            action: 'backup.download',
            userId: $request->user()->id,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()
            ->download($path, $this->backups->filename())
            ->deleteFileAfterSend();
    }

    public function restore(RestoreUploadRequest $request): RedirectResponse
    {
        try {
            $this->backups->restore($request->file('file')->getRealPath());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        // Logged before the redirect, not after: a restore just replaced the
        // activity_logs table itself, so this row is deliberately the first
        // thing written into the restored database rather than something a
        // wiped table could have raced.
        $this->activity->log(
            action: 'backup.restore',
            userId: $request->user()->id,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.backup')->with('status', 'تم استرجاع النظام بنجاح. الداتا والملفات رجعت زي وقت الباك أب.');
    }
}
