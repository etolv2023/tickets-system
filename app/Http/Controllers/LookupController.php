<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Label;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Type-ahead source for every dropdown whose options come from the database.
 *
 * A select that renders every row works until the table grows: five hundred
 * companies is a 40KB page and an unusable list. These endpoints return the
 * first handful of matches instead, so page weight stays flat no matter how
 * big the data gets.
 *
 * Each resource authorises for itself. A lookup that skipped that would be a
 * neat way to enumerate every company name in the system from any account.
 */
class LookupController extends Controller
{
    private const LIMIT = 20;

    public function __invoke(Request $request, string $resource): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $user = $request->user();

        $results = match ($resource) {
            'companies' => $this->companies($term),
            'contacts' => $this->contacts($request, $term),
            'users' => $this->users($term),
            'labels' => $this->labels($term),
            'tickets' => $this->tickets($user, $term),
            default => abort(404),
        };

        return response()->json(['results' => $results]);
    }

    /** @return array<int, array<string, mixed>> */
    private function companies(string $term): array
    {
        return Company::query()
            ->select(['id', 'name', 'code'])
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Company $c) => ['id' => $c->id, 'label' => $c->name, 'hint' => $c->code])
            ->all();
    }

    /** Contacts are always scoped to one company — never listed globally. */
    private function contacts(Request $request, string $term): array
    {
        $companyId = (int) $request->query('company');

        if ($companyId === 0) {
            return [];
        }

        return CompanyContact::query()
            ->select(['id', 'name', 'email'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (CompanyContact $c) => ['id' => $c->id, 'label' => $c->name, 'hint' => $c->email])
            ->all();
    }

    private function users(string $term): array
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'label' => $u->name, 'hint' => $u->email])
            ->all();
    }

    private function labels(string $term): array
    {
        return Label::query()
            ->select(['id', 'name', 'color'])
            ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Label $l) => ['id' => $l->id, 'label' => $l->name, 'variant' => $l->color])
            ->all();
    }

    /** Scoped to what this person may see — the same rule the list screen uses. */
    private function tickets(User $user, string $term): array
    {
        return Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'status'])
            ->visibleTo($user)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('ticket_number', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")))
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Ticket $t) => [
                'id' => $t->id,
                'label' => $t->title,
                'hint' => $t->ticket_number,
            ])
            ->all();
    }
}
