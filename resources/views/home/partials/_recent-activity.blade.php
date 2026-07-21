{{-- ★★★★ New (2026-07-20). Same gate as /admin/audit — null means "can't see
     this". ★★★★★ (2026-07-21) rendered as a compact activity feed (icon + actor
     + verb + mono time) instead of a wide 4-column table, so it stays a dense
     rail and its empty state is a one-line strip — never the full-width empty
     box it used to be. --}}
@if ($recentActivity !== null)
    <x-card title="آخر نشاط" flush>
        <x-slot:actions>
            <a class="today__q-hint" href="{{ route('admin.audit.index') }}">السجل كامل</a>
        </x-slot:actions>

        <div class="activity-feed">
            @forelse ($recentActivity as $log)
                <div class="activity-row">
                    <x-icon name="activity" class="activity-row__glyph" />
                    <span class="activity-row__actor">{{ $log->actor_name ?? 'النظام' }}</span>
                    <span class="activity-row__verb u-mono u-ltr">{{ $log->action }}</span>
                    @if ($log->subject_type)
                        <span class="activity-row__subject u-mono">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</span>
                    @endif
                    <span class="activity-row__time">
                        {{ $log->created_at->timezone(config('app.display_timezone'))->diffForHumans() }}
                    </span>
                </div>
            @empty
                <div class="today__clear">
                    <x-icon name="check-circle" class="today__clear-glyph" />
                    مفيش نشاط مسجّل لسه.
                </div>
            @endforelse
        </div>
    </x-card>
@endif
