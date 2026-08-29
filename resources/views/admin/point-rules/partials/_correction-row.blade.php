{{-- One row of the corrections ledger.

     Three shapes, and the row says which one it is rather than leaving you to
     work it out from a negative number:

       · a correction that still stands     — normal, with its actions
       · a correction somebody cancelled    — struck through, «اتلغى» / «اتعدّل»
       · the reversing row itself           — «سطر إلغاء», pointing back

     The cancelled rows are NOT hidden. That is the whole reason this was built
     as reversing entries instead of deletes: what was written stays readable,
     and the sum below it is still right because the reversal is in the sum. --}}

@php
    // Presentation only — which of the three shapes this row is. No rule is
    // decided here; every one of these is a method on the model.
    $void = $correction->isReversed();
@endphp

{{-- Not .points-row--void: .points-row is the leaderboard's row on a
     different screen, and a modifier of it here would read as the same thing. --}}
<tr @class(['correction--void' => $void])>
    <td>{{ $correction->user->name }}</td>
    <td>{{ $correction->role?->name_ar ?? $correction->side?->label() ?? '—' }}</td>
    <td class="table__cell--num points-cell">{{ rtrim(rtrim($correction->points, '0'), '.') }}</td>

    <td class="u-subtle">
        {{ $correction->reason }}

        @if ($correction->isReversal())
            <x-badge variant="slate">سطر إلغاء</x-badge>
        @elseif ($correction->isReplaced())
            <x-badge variant="amber">اتعدّل</x-badge>
        @elseif ($void)
            <x-badge variant="slate">اتلغى</x-badge>
        @endif
    </td>

    <td>
        @if ($correction->ticket)
            <a href="{{ route('tickets.show', $correction->ticket) }}" class="u-mono u-ltr">
                {{ $correction->ticket->ticket_number }}
            </a>
        @else
            <span class="u-subtle">—</span>
        @endif
    </td>

    <td class="u-subtle">{{ $correction->correctedBy?->name }}</td>
    <td class="u-subtle u-mono">{{ $correction->created_at->format('Y-m-d H:i') }}</td>

    <td class="table__cell--actions">
        {{-- Nothing to offer on a row that is already settled: a reversing entry
             cannot itself be reversed, and a cancelled correction has nothing
             left to cancel. Both are refused in the service too — this only
             keeps a dead button off the screen. --}}
        @unless ($correction->isReversal() || $void)
            <div class="row">
                @can('points.corrections.edit')
                    <x-button variant="ghost" size="sm"
                              x-on:click="editing = (editing === {{ $correction->id }} ? null : {{ $correction->id }})">
                        تعديل
                    </x-button>
                @endcan

                @can('points.corrections.delete')
                    {{-- The confirmation hangs off the BUTTON, not off the form.

                         onsubmit="return confirm(…)" is the pattern used by the
                         other delete buttons in this app, and it has a trap: the
                         submit guard runs in the capture phase, so it locks the
                         form and disables the button BEFORE the dialog is even
                         shown. Answer "لأ" once — or meet a browser that
                         suppressed the dialog — and the button is dead until a
                         reload, silently. On a click handler the cancelled
                         confirm never produces a submit event at all, so there
                         is nothing to lock and nothing to unlock.

                         (submit-guard.js now also unlocks a prevented submit, so
                         the other screens are covered too. This form does not
                         depend on that fix.) --}}
                    <form method="POST" action="{{ route('admin.point-rules.corrections.destroy', $correction) }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" size="sm"
                                  onclick="return confirm('هيتكتب سطر عكسي يلغي التصحيح ده. الأصلي هيفضل ظاهر في الدفتر. تمام؟')">
                            إلغاء
                        </x-button>
                    </form>
                @endcan
            </div>
        @endunless
    </td>
</tr>

@can('points.corrections.edit')
    @unless ($correction->isReversal() || $void)
        <tr x-show="editing === {{ $correction->id }}" x-cloak>
            <td colspan="8">
                @include('admin.point-rules.partials._correction-edit', ['correction' => $correction])
            </td>
        </tr>
    @endunless
@endcan
