@php
    /*
     * F10: a link is stored once, from one side. The outgoing rows read with
     * their own label ("بتبلوك"); the incoming rows are the SAME rows seen from
     * the other end, so they read with the inverse ("مبلوكة بـ").
     */
    $rows = $ticket->outgoingLinks
        ->map(fn ($l) => ['link' => $l, 'label' => $l->type->label(), 'other' => $l->toTicket])
        ->concat($ticket->incomingLinks
            ->map(fn ($l) => ['link' => $l, 'label' => $l->type->inverseLabel(), 'other' => $l->fromTicket]))
        ->filter(fn ($row) => $row['other'] !== null);
@endphp

<x-card title="الروابط">
    <div class="stack stack--tight">
        @if ($ticket->isBlocked())
            <x-alert variant="error">
                التذكرة دي مبلوكة بتذكرة تانية لسه مقفلتش.
            </x-alert>
        @endif

        @if ($rows->isEmpty())
            <p class="field__hint">مفيش روابط.</p>
        @else
            <div class="links">
                @foreach ($rows as $row)
                    <div class="link-row">
                        <span class="link-row__type">{{ $row['label'] }}</span>
                        <a class="link-row__ticket" href="{{ route('tickets.show', $row['other']) }}">
                            <span class="u-mono u-ltr">{{ $row['other']->ticket_number }}</span>
                            {{ Str::limit($row['other']->title, 30) }}
                        </a>
                        <x-badge :variant="$row['other']->status->variant()">{{ $row['other']->status->label() }}</x-badge>

                        @can('links.manage')
                            <form method="POST" action="{{ route('tickets.links.destroy', [$ticket, $row['link']]) }}">
                                @csrf
                                @method('DELETE')
                                <x-button variant="ghost" size="sm">×</x-button>
                            </form>
                        @endcan
                    </div>
                @endforeach
            </div>
        @endif

        @can('links.manage')
            <hr class="divider">

            <form method="POST" action="{{ route('tickets.links.store', $ticket) }}" class="stack stack--tight">
                @csrf

                <x-field name="type" label="نوع الربط">
                    <select id="type" name="type" class="select" required>
                        @foreach (\App\Enums\LinkType::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>

                {{-- Searched server-side by number OR title: nobody remembers
                     TK-2026-00147, they remember "the double-tax invoice one". --}}
                <x-combobox name="to_ticket_id" resource="tickets" label="التذكرة" required
                            placeholder="اكتب رقم التذكرة أو جزء من عنوانها…" />

                <x-button variant="secondary" size="sm" block>اربط</x-button>
            </form>
        @endcan
    </div>
</x-card>
