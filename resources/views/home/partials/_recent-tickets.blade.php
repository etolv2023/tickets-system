{{-- ★★★★ New (2026-07-20). Same row shape as tickets/index, capped to the
     latest few and scoped to what this user can see. Empty state is the shared
     one-line all-clear strip, never a padded void. --}}
<x-card title="آخر التذاكر" flush>
    <x-slot:actions>
        <a class="today__q-hint" href="{{ route('tickets.index') }}">عرض الكل</a>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th>الرقم</th>
                    <th>العنوان</th>
                    <th>الحالة</th>
                    <th>العمر</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentTickets as $ticket)
                    <tr class="tickets__row">
                        <td class="table__cell--tight">
                            <span class="tickets__number">{{ $ticket->ticket_number }}</span>
                        </td>
                        <td>
                            <a class="tickets__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                        </td>
                        <td class="table__cell--tight">
                            <x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge>
                        </td>
                        <td class="table__cell--tight">
                            <span @class(['tickets__age', 'tickets__age--overdue' => $ticket->isOverdue()])>
                                {{ $ticket->ageLabel() }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="today__clear">
                                <x-icon name="check-circle" class="today__clear-glyph" />
                                مفيش تذاكر لسه.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
