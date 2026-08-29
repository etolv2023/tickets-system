{{-- One repository, as a card rather than a table row.

     A row was the first shape tried, following the link-types screen. It does
     not survive six editable fields: they wrap onto five lines each and the
     "table" becomes a stack of tall cells with headers that no longer describe
     what is under them — and on a phone it needs horizontal scrolling to reach
     the last field (CLAUDE.md § 6, الموبايل). Four repositories do not need a
     table; they need four cards.

     NO DELETE, and no route behind one either: every branch recorded against
     this repository points at this row. «مفعّل» off takes it out of the sync
     and out of the manual-link picker, and leaves the history readable. --}}

<x-card>
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.github.sync', $repository) }}">
            @csrf
            <x-button variant="ghost" size="sm">
                <x-icon name="refresh" class="btn__icon" />
                زامن دلوقتي
            </x-button>
        </form>
    </x-slot:actions>

    <div class="stack stack--tight">
        <div class="gh-repo">
            <span class="gh-repo__name">{{ $repository->name }}</span>

            <a class="u-mono u-ltr" href="{{ $repository->webUrl() }}" target="_blank" rel="noopener noreferrer">
                <x-icon name="external" size="0.9em" />
                {{ $repository->fullName() }}
            </a>

            @unless ($repository->is_active)
                <x-badge variant="slate">مقفول</x-badge>
            @endunless

            <span class="gh-repo__stat u-mono">
                {{ $branchCounts[$repository->id] ?? 0 }} برانش · {{ $pullCounts[$repository->id] ?? 0 }} PR
            </span>

            @if ($repository->last_sync_error)
                <span class="gh-repo__error">{{ Str::limit($repository->last_sync_error, 80) }}</span>
            @elseif ($repository->last_synced_at)
                <span class="gh-repo__stat u-mono">
                    آخر مزامنة
                    {{ $repository->last_synced_at->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}
                </span>
            @else
                <span class="u-subtle">لسه مزامنتش</span>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.github.update', $repository) }}" class="form-grid">
            @csrf
            @method('PUT')

            <x-field :name="'name_' . $repository->id" label="الاسم" required>
                <input type="text" id="name_{{ $repository->id }}" name="name"
                       value="{{ $repository->name }}" class="input" required>
            </x-field>

            <x-field :name="'owner_' . $repository->id" label="المالك" required>
                <input type="text" id="owner_{{ $repository->id }}" name="owner" dir="ltr"
                       value="{{ $repository->owner }}" class="input u-mono u-ltr" required>
            </x-field>

            <x-field :name="'repo_' . $repository->id" label="الريبو" required>
                <input type="text" id="repo_{{ $repository->id }}" name="repo" dir="ltr"
                       value="{{ $repository->repo }}" class="input u-mono u-ltr" required>
            </x-field>

            <x-field :name="'side_' . $repository->id" label="الجانب">
                <select id="side_{{ $repository->id }}" name="side" class="select">
                    <option value="">— من غير جانب —</option>
                    @foreach ($sides as $value => $label)
                        <option value="{{ $value }}" @selected($repository->side?->value === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field :name="'default_branch_' . $repository->id" label="الفرع الافتراضي" required>
                <input type="text" id="default_branch_{{ $repository->id }}" name="default_branch" dir="ltr"
                       value="{{ $repository->default_branch }}" class="input u-mono u-ltr" required>
            </x-field>

            <div class="gh-repo__foot">
                <label class="gh-repo__toggle">
                    <input type="checkbox" name="is_active" value="1" @checked($repository->is_active)>
                    مفعّل — يتقرا في المزامنة
                </label>

                <x-button variant="secondary" size="sm">حفظ</x-button>
            </div>
        </form>
    </div>
</x-card>
