@extends('layouts.app')

@section('title', __('التذاكر'))

@section('content')
    <div class="page page--wide"
         x-data="tableColumns({ storageKey: 'tickets.index.columns.v2', defaults: ['priority', 'status', 'assignees', 'deadline'] })">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        {{-- A status change can be refused from here now (an unfinished
             subtask, a client not yet notified), so the refusal has to have
             somewhere to land. --}}
        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        {{-- No page heading above the filters: the nav already says where you
             are, and the design puts the "new ticket" action inside the bar. --}}
        @include('tickets.partials._filters')

        {{-- Genuinely empty — day one, no ticket opened yet — is a different
             screen from "no results for this filter" and gets the one
             message worth spending words on (blank.css). --}}
        @if ($tickets->total() === 0 && ! array_filter($filters))
            <x-card>
                <div class="blank">
                    <p class="blank__title">{{ __('لا توجد تذاكر بعد.') }}</p>
                    <p class="blank__body">
                        {{ __('عند إنشاء أول تذكرة ستظهر هنا — مرتبة حسب الأولوية، ثم الأقدم.') }}
                    </p>
                    @can('create', App\Models\Ticket::class)
                        <div class="blank__actions">
                            <x-button variant="primary" :href="route('tickets.create')">
                                <x-icon name="plus" class="btn__icon" />
                                {{ __('تذكرة جديدة') }}
                            </x-button>
                        </div>
                    @endcan
                </div>
            </x-card>
        @else
            <x-card flush>
                <div class="table-wrap">
                    <table class="table table--hover table--zebra tickets__table">
                        <thead>
                            <tr>
                                <th>{{ __('التذكرة') }}</th>
                                <th x-show="isVisible('priority')">{{ __('الأولوية') }}</th>
                                <th x-show="isVisible('status')">{{ __('الحالة') }}</th>
                                <th x-show="isVisible('assignees')">{{ __('المسؤولين') }}</th>
                                <th x-show="isVisible('creator')">{{ __('أنشأها') }}</th>
                                <th x-show="isVisible('date')">{{ __(\App\Models\Ticket::DATE_BASES[$dateBasis]) }}</th>
                                <th x-show="isVisible('deadline')">{{ __('الموعد والعمر') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tickets as $ticket)
                                @include('tickets.partials._row', ['ticket' => $ticket])
                            @empty
                                <tr class="table__empty">
                                    <td colspan="7">{{ __('لا توجد تذاكر مطابقة لعوامل التصفية.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- The count reads as a footnote under the table, not as a heading
                 above it — you look at it after scanning, if at all. --}}
            <span class="tickets__count">
                {{ trans_choice(':count تذكرة|تذكرة واحدة|تذكرتان|:count تذاكر|:count تذكرة|:count تذكرة', $tickets->total()) }} · {{ __('الترتيب: :sort', ['sort' => __(\App\Models\Ticket::SORTS[$filters['sort'] ?? 'default'] ?? \App\Models\Ticket::SORTS['default'])]) }}.
            </span>

            {{ $tickets->links() }}
        @endif
    </div>
@endsection
