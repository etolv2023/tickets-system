{{-- ★★★★★ (2026-07-21) Breach rate by priority as a horizontal bar-meter list,
     not a 2-column table — a bar filled to count/max in that priority's own hue
     shows which priority is bleeding worst at a glance. The percent is computed
     in DashboardService::slaBreachesByPriority (guarded against an all-zero
     month), never here (CLAUDE.md § 3). Meters are explicitly allowed; this is a
     bar, not a chart. --}}
<x-card title="نظرة على الـ SLA" flush>
    <x-slot:actions>
        <span class="u-subtle">خروقات الشهر ده</span>
    </x-slot:actions>

    @forelse ($slaByPriority as $row)
        <div class="meter-row">
            <x-badge :variant="$row['priority']->variant()" :icon="$row['priority']->icon()">
                {{ $row['priority']->label() }}
            </x-badge>
            <div class="meter meter--{{ $row['priority']->variant() }}">
                <div class="meter__fill" style="--value: {{ $row['pct'] }}%"></div>
            </div>
            <span class="meter__num">{{ $row['count'] }}</span>
        </div>
    @empty
        <div class="today__clear">
            <x-icon name="check-circle" class="today__clear-glyph" />
            مفيش خرق للمهلة الشهر ده.
        </div>
    @endforelse
</x-card>
