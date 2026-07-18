<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactRequest;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Contacts live under their company: /admin/companies/{company}/contacts */
class ContactController extends Controller
{
    /**
     * A global "find this person" screen across every company — for when you
     * know the contact but not which customer they belong to.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Company::class);

        return view('admin.contacts.index', [
            'contacts' => CompanyContact::query()
                ->select(['id', 'company_id', 'name', 'erp_employee_id', 'email', 'phone', 'is_active'])
                ->with('company:id,name,code')
                ->search($request->query('q'))
                ->when($request->query('company'), fn ($q, $v) => $q->where('company_id', $v))
                ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
                ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('q', 'company', 'status'),
            // Only the selected name; the list comes from /lookup.
            'selectedCompany' => filled($request->query('company'))
                ? Company::whereKey($request->query('company'))->value('name')
                : null,
        ]);
    }

    public function create(Company $company): View
    {
        $this->authorize('update', $company);

        return view('admin.contacts.create', ['company' => $company]);
    }

    public function store(ContactRequest $request, Company $company, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $company);

        $contact = $company->contacts()->create($request->validated());

        $logger->log(
            action: 'contact.created',
            userId: $request->user()->id,
            subject: $contact,
            changes: ['to' => $contact->only('name', 'erp_employee_id')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.companies.show', $company)
            ->with('status', "تم إضافة «{$contact->name}».");
    }

    public function edit(Company $company, CompanyContact $contact): View
    {
        $this->authorize('update', $company);
        $this->assertBelongsTo($company, $contact);

        return view('admin.contacts.edit', ['company' => $company, 'contact' => $contact]);
    }

    public function update(
        ContactRequest $request,
        Company $company,
        CompanyContact $contact,
        ActivityLogger $logger
    ): RedirectResponse {
        $this->authorize('update', $company);
        $this->assertBelongsTo($company, $contact);

        $before = $contact->only('name', 'erp_employee_id', 'email', 'phone', 'is_active');
        $contact->update($request->validated());

        $logger->log(
            action: 'contact.updated',
            userId: $request->user()->id,
            subject: $contact,
            changes: ['from' => $before, 'to' => $contact->only('name', 'erp_employee_id', 'email', 'phone', 'is_active')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.companies.show', $company)->with('status', 'تم حفظ التعديلات.');
    }

    public function destroy(
        Request $request,
        Company $company,
        CompanyContact $contact,
        ActivityLogger $logger
    ): RedirectResponse {
        $this->authorize('update', $company);
        $this->assertBelongsTo($company, $contact);

        $name = $contact->name;

        $logger->log(
            action: 'contact.deleted',
            userId: $request->user()->id,
            subject: $contact,
            changes: ['from' => $contact->only('name', 'erp_employee_id')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $contact->delete();

        return redirect()->route('admin.companies.show', $company)->with('status', "تم حذف «{$name}».");
    }

    /**
     * Nested bindings are matched independently, so /companies/1/contacts/99
     * would happily load a contact from company 2 without this (IDOR, § 5).
     */
    private function assertBelongsTo(Company $company, CompanyContact $contact): void
    {
        abort_unless($contact->company_id === $company->id, 404);
    }
}
