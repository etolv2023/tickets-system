<?php

namespace App\Exports;

use App\Exports\Concerns\CachesSheets;
use App\Exports\Sheets\ArraySheet;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketSubtask;
use App\Models\User;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * F21 — the search results, one tab per group.
 *
 * Every query here is visibility-scoped exactly as the screen is: an export is
 * not a back door around row-level access, and a search export especially so —
 * a hit on a ticket you may not open leaks that the ticket exists.
 *
 * The same limits the screen uses apply, deliberately: this exports the result
 * list you are looking at, not a second, wider search run behind your back.
 */
class SearchExport implements WithMultipleSheets
{
    use Exportable, CachesSheets;

    public function __construct(
        private readonly User $user,
        private readonly string $term,
    ) {
    }

    /** @return array<int, ArraySheet> */
    protected function buildSheets(): array
    {
        return array_values(array_filter([
            $this->tickets(),
            $this->subtasks(),
            $this->comments(),
            $this->companies(),
        ]));
    }

    private function tickets(): ArraySheet
    {
        $rows = Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'company_id', 'requested_by', 'type', 'priority', 'status'])
            ->with('company:id,name', 'requester:id,name')
            ->whereFullText(['title', 'description'], $this->term)
            ->visibleTo($this->user)
            ->limit(20)
            ->get();

        return new ArraySheet(
            'التذاكر',
            ['رقم التذكرة', 'العنوان', 'الجهة الطالبة', 'النوع', 'الأولوية', 'الحالة'],
            $rows->map(fn ($t) => [
                $t->ticket_number,
                $t->title,
                $t->originLabel(),
                $t->type->label(),
                $t->priority->label(),
                $t->status->label(),
            ])->all(),
        );
    }

    private function subtasks(): ArraySheet
    {
        $rows = TicketSubtask::query()
            ->select(['id', 'ticket_id', 'title', 'status', 'side', 'role_id'])
            ->with('ticket:id,ticket_number,title', 'role:id,name_ar')
            ->where('title', 'like', "%{$this->term}%")
            ->whereHas('ticket', fn ($q) => $q->visibleTo($this->user))
            ->limit(15)
            ->get();

        return new ArraySheet(
            'الصب تاسكس',
            ['رقم التذكرة', 'عنوان التذكرة', 'الصب تاسك', 'الجهة / الدور', 'الحالة'],
            $rows->map(fn ($s) => [
                $s->ticket?->ticket_number,
                $s->ticket?->title,
                $s->title,
                $s->role?->name_ar ?? $s->side?->label() ?? '—',
                $s->status->label(),
            ])->all(),
        );
    }

    private function comments(): ArraySheet
    {
        $rows = TicketComment::query()
            ->select(['id', 'ticket_id', 'user_id', 'contact_id', 'body', 'is_internal', 'created_at'])
            ->with(['ticket:id,ticket_number,title', 'user:id,name', 'contact:id,name'])
            ->where('body', 'like', "%{$this->term}%")
            ->whereHas('ticket', fn ($q) => $q->visibleTo($this->user))
            ->unless($this->user->hasPermission('comments.internal'), fn ($q) => $q->where('is_internal', false))
            ->limit(15)
            ->get();

        return new ArraySheet(
            'التعليقات',
            ['التاريخ', 'رقم التذكرة', 'عنوان التذكرة', 'صاحب التعليق', 'داخلي', 'التعليق'],
            $rows->map(fn ($c) => [
                $c->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
                $c->ticket?->ticket_number,
                $c->ticket?->title,
                $c->user?->name ?? $c->contact?->name,
                $c->is_internal ? 'أيوه' : 'لأ',
                // The stored HTML is already purified; strip it back to prose
                // so no markup lands in a cell.
                Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($c->body))), 500),
            ])->all(),
        );
    }

    /** Null when this user may not see the customer table at all. */
    private function companies(): ?ArraySheet
    {
        if (! $this->user->hasPermission('companies.manage')) {
            return null;
        }

        $rows = Company::search($this->term)->limit(10)->get(['id', 'name', 'code', 'is_active']);

        return new ArraySheet(
            'الشركات',
            ['الشركة', 'الكود', 'الحالة'],
            $rows->map(fn ($c) => [$c->name, $c->code, $c->is_active ? 'مفعّلة' : 'موقوفة'])->all(),
        );
    }
}
