<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyRequest;
use App\Models\Company;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Company::class);

        return view('admin.companies.index', [
            'companies' => Company::query()
                ->select(['id', 'name', 'code', 'is_active'])
                ->withCount('contacts')
                ->search($request->query('q'))
                ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
                ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('q', 'status'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Company::class);

        return view('admin.companies.create');
    }

    public function store(CompanyRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('create', Company::class);

        $company = Company::create($request->validated());

        $logger->log(
            action: 'company.created',
            userId: $request->user()->id,
            subject: $company,
            changes: ['to' => $company->only('name', 'code', 'is_active')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.companies.show', $company)
            ->with('status', "تم إضافة «{$company->name}».");
    }

    /** F01: the company page answers "is this customer heavy or quiet?" */
    public function show(Company $company): View
    {
        $this->authorize('view', $company);

        return view('admin.companies.show', [
            'company' => $company,
            'contacts' => $company->contacts()
                ->select(['id', 'company_id', 'name', 'erp_employee_id', 'email', 'phone', 'is_active'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(20),
            // Ticket counts and average resolution time join here in phase 2,
            // when there are tickets to count.
        ]);
    }

    public function edit(Company $company): View
    {
        $this->authorize('update', $company);

        return view('admin.companies.edit', ['company' => $company]);
    }

    public function update(CompanyRequest $request, Company $company, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $company);

        $before = $company->only('name', 'code', 'is_active', 'notes');
        $company->update($request->validated());

        $logger->log(
            action: 'company.updated',
            userId: $request->user()->id,
            subject: $company,
            changes: ['from' => $before, 'to' => $company->only('name', 'code', 'is_active', 'notes')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.companies.show', $company)->with('status', 'تم حفظ التعديلات.');
    }

    public function destroy(Request $request, Company $company, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('delete', $company);

        $name = $company->name;

        $logger->log(
            action: 'company.deleted',
            userId: $request->user()->id,
            subject: $company,
            changes: ['from' => $company->only('name', 'code')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $company->delete();

        return redirect()->route('admin.companies.index')->with('status', "تم حذف «{$name}».");
    }
}
