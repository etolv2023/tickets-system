<div class="page__head">
    <div>
        <span class="ticket__number">{{ $ticket->ticket_number }}</span>
        <h1 class="ticket__title">{{ $ticket->title }}</h1>
        <div class="ticket__badges">
            <x-badge :variant="$ticket->type->variant()" :icon="$ticket->type->icon()">{{ $ticket->type->label() }}</x-badge>
            <x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge>
            <x-badge variant="neutral">{{ $ticket->scope->label() }}</x-badge>
            @if ($ticket->module)
                <x-badge variant="neutral">{{ $ticket->module }}</x-badge>
            @endif
            @foreach ($ticket->labels as $label)
                <x-badge :variant="$label->color">{{ $label->name }}</x-badge>
            @endforeach
            @if ($ticket->isBlocked())
                <span class="blocked-flag">
                    <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM5.3 5.3a1 1 0 011.4 0l7 7a1 1 0 01-1.4 1.4l-7-7a1 1 0 010-1.4z" clip-rule="evenodd" />
                    </svg>
                    مبلوكة
                </span>
            @endif
        </div>
    </div>
    {{-- Each button carries its own gate: deleting and editing are separate
         permissions, so the wrapper can't belong to either. --}}
    {{-- No status control here: the "غيّر الحالة" panel below owns that on this
         screen, and two pickers for one field read as a bug. --}}
    @canany(['update', 'delete'], $ticket)
        <div class="page__actions">
            @can('update', $ticket)
                <x-button variant="ghost" :href="route('tickets.edit', $ticket)">تعديل</x-button>
            @endcan
            @can('delete', $ticket)
                <form method="POST" action="{{ route('tickets.destroy', $ticket) }}"
                      onsubmit="return confirm('متأكد إنك عايز تحذف {{ $ticket->ticket_number }}؟ هتتشال من كل القوايم هي والصب تاسكس بتاعتها.')">
                    @csrf
                    @method('DELETE')
                    <x-button variant="ghost" size="sm">حذف</x-button>
                </form>
            @endcan
        </div>
    @endcanany
</div>

