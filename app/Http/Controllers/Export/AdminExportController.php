<?php

namespace App\Http\Controllers\Export;

use App\Exports\AuditExport;
use App\Exports\CompaniesExport;
use App\Exports\ContactsExport;
use App\Exports\MoneyExport;
use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Export\Concerns\LogsExport;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The admin-table exports.
 *
 * These carry staff emails, customer contact details, the audit trail and the
 * month's payroll figures, so each one repeats its own screen's gate rather
 * than sharing one broad "admin" check.
 */
class AdminExportController extends Controller
{
    use LogsExport;

    /**
     * F18.3 — /admin/point-values: the month in money.
     *
     * Gated on settings.manage, exactly as the screen is. Deliberately NOT
     * points.rules.manage: that authority is about correcting individual ledger
     * rows, not about reading what everyone is owed.
     */
    public function money(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $period = $this->period($request);

        $this->logExport($request, 'export.money', ['period' => $period]);

        return (new MoneyExport($period))->download("money-{$period}.xlsx");
    }

    /** F23 — /admin/audit */
    public function audit(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('audit.view'), 403);

        $filters = $request->only('user', 'action', 'subject', 'from', 'to');

        $this->logExport($request, 'export.audit', $filters);

        return (new AuditExport($filters))->download($this->filename('audit'));
    }

    /** F22.3 — /admin/users */
    public function users(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only('q', 'role', 'status');

        $this->logExport($request, 'export.users', $filters);

        return (new UsersExport($filters))->download($this->filename('users'));
    }

    /** F01 — /admin/companies */
    public function companies(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Company::class);

        $filters = $request->only('q', 'status');

        $this->logExport($request, 'export.companies', $filters);

        return (new CompaniesExport($filters))->download($this->filename('companies'));
    }

    /** F01 — /admin/contacts */
    public function contacts(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Company::class);

        $filters = $request->only('q', 'company', 'status');

        $this->logExport($request, 'export.contacts', $filters);

        return (new ContactsExport($filters))->download($this->filename('contacts'));
    }
}
