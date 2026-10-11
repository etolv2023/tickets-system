{{-- ★★★★ New (2026-07-20). null (not empty) means "no reports.view" — same
     gate /reports itself sits behind. ★★★★★ (2026-07-21) adds a load meter
     scaled to the busiest person shown; the per-side counts stay in the table
     (the bar is additive, never a replacement for the figures). load_pct is
     computed in DashboardService::teamPerformance, never here (CLAUDE.md § 3). --}}
@if ($teamPerformance !== null)
    <x-card :title="__('ui.today.team_load')" flush>
        <x-slot:actions>
            <a class="today__q-hint" href="{{ route('reports.index') }}">{{ __('ui.today.full_report') }}</a>
        </x-slot:actions>

        <div class="table-wrap">
            <table class="table table--hover">
                <thead>
                    <tr>
                        <th>{{ __('ui.today.employee') }}</th>
                        <th class="table__cell--num">{{ __('ui.today.open_tickets') }}</th>
                        <th>{{ __('ui.today.load') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teamPerformance as $person)
                        <tr>
                            <td>
                                <div class="row">
                                    <x-avatar :user="$person" size="sm" />
                                    {{ $person->name }}
                                </div>
                            </td>
                            <td class="table__cell--num">{{ $person->load_total }}</td>
                            <td>
                                <div class="meter meter--slate">
                                    <div class="meter__fill" style="--value: {{ $person->load_pct }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <div class="today__clear">
                                    <x-icon name="check-circle" class="today__clear-glyph" />
                                    {{ __('ui.today.no_open_work') }}
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endif
