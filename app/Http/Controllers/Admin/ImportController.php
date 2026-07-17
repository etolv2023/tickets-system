<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportType;
use App\Exports\ImportErrorsExport;
use App\Exports\ImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportUploadRequest;
use App\Jobs\RunImport;
use App\Models\ImportBatch;
use App\Services\ActivityLogger;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * F02: template -> upload -> validate + preview -> confirm -> report.
 * Nothing is written before the confirm step.
 */
class ImportController extends Controller
{
    public function __construct(private readonly ImportService $importer)
    {
    }

    public function index(Request $request): View
    {
        $this->authorizeAnyImport($request);

        return view('admin.import.index', [
            'types' => $this->allowedTypes($request),
            'batches' => ImportBatch::with('user:id,name')
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    public function template(Request $request, string $type): BinaryFileResponse
    {
        $importType = $this->resolveType($request, $type);

        return (new ImportTemplateExport($importType))
            ->download("template-{$importType->value}.xlsx");
    }

    /** Reads and validates, but writes nothing — the preview comes next. */
    public function upload(ImportUploadRequest $request): RedirectResponse
    {
        $type = ImportType::from($request->validated('type'));

        try {
            $batch = $this->importer->storeUpload($request->file('file'), $type, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        try {
            $this->importer->analyse($batch);
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'errors' => [['row' => 0, 'column' => '-', 'reason' => $e->getMessage()]]]);

            return redirect()->route('admin.import.index')->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('admin.import.preview', $batch);
    }

    public function preview(Request $request, ImportBatch $batch): View|RedirectResponse
    {
        $this->resolveType($request, $batch->type->value);

        if (in_array($batch->status, ['completed', 'importing'], true)) {
            return redirect()->route('admin.import.report', $batch);
        }

        $analysis = $this->importer->analyse($batch);

        return view('admin.import.preview', [
            'batch' => $batch->refresh(),
            'counts' => $analysis['counts'],
            // F02 asks for the first 50 rows with each row's verdict.
            'rows' => array_slice($analysis['rows'], 0, 50),
            'truncated' => count($analysis['rows']) > 50,
        ]);
    }

    public function confirm(Request $request, ImportBatch $batch, ActivityLogger $logger): RedirectResponse
    {
        $this->resolveType($request, $batch->type->value);

        $logger->log(
            action: 'import.confirmed',
            userId: $request->user()->id,
            subject: $batch,
            changes: ['type' => $batch->type->value, 'file' => $batch->original_filename],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        // Small files finish before the redirect and need no worker running;
        // big ones would time out a request, so they go to the queue. F02
        if ($batch->total_rows > ImportService::QUEUE_THRESHOLD) {
            $batch->update(['status' => 'importing']);
            RunImport::dispatch($batch->id);

            return redirect()->route('admin.import.report', $batch)
                ->with('status', 'الملف كبير، فالاستيراد بيتعمل في الخلفية. حدّث الصفحة بعد شوية.');
        }

        try {
            $this->importer->commit($batch);
        } catch (\Throwable $e) {
            return redirect()->route('admin.import.report', $batch)
                ->withErrors(['import' => 'فشل الاستيراد: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.import.report', $batch);
    }

    public function report(Request $request, ImportBatch $batch): View
    {
        $this->resolveType($request, $batch->type->value);

        return view('admin.import.report', ['batch' => $batch]);
    }

    public function errors(Request $request, ImportBatch $batch): BinaryFileResponse
    {
        $this->resolveType($request, $batch->type->value);

        abort_if(blank($batch->errors), 404);

        return (new ImportErrorsExport($batch))->download("errors-{$batch->id}.xlsx");
    }

    /** Each import type has its own permission (F02); check the specific one. */
    private function resolveType(Request $request, string $type): ImportType
    {
        $importType = ImportType::tryFrom($type);

        abort_if($importType === null, 404);
        abort_unless($request->user()->hasPermission($importType->permission()), 403);

        return $importType;
    }

    private function authorizeAnyImport(Request $request): void
    {
        abort_if($this->allowedTypes($request) === [], 403);
    }

    /** @return array<int, ImportType> */
    private function allowedTypes(Request $request): array
    {
        return array_values(array_filter(
            ImportType::cases(),
            fn (ImportType $t) => $request->user()->hasPermission($t->permission())
        ));
    }
}
