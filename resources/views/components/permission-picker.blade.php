@props([
    /* Collection<string groupKey, Collection<Permission>> */
    'groups',
    /* Permission ids that are on. For the role form this is the role's own set. */
    'selected' => [],
    /* Field name posted back. Roles post permissions[], users post grants[]. */
    'name' => 'permissions',
    /* Prefix for the DOM ids, so two pickers can coexist on one page. */
    'idPrefix' => 'perm',
    /*
     * Optional third state. When given, a permission NOT in $selected and NOT in
     * this list renders as "inherited" rather than plain-off — the users screen
     * uses it to show what the role already grants. Ids only.
     */
    'inherited' => null,
])

{{-- Was one column of forty-odd checkboxes on the roles screen: no grouping you
     could see, no way to tell how much of a section was on, and a scroll to
     reach the save button. Each group is its own panel with its own count and a
     select-all, and the panels flow into however many columns fit.

     ★ (2026-08-02) Extracted out of admin/roles/partials/_form so the per-user
     override screen renders the identical control instead of a second copy that
     drifts from it. --}}
@foreach ($groups as $groupKey => $permissions)
    @php
        $chosen = old($name, $selected) ?? [];
        $onCount = $permissions->filter(fn ($p) => in_array($p->id, $chosen, false))->count();
    @endphp

    <div x-data="{
        total: {{ $permissions->count() }},
        on: {{ $onCount }},
        toggleAll(state) {
            this.$refs.list.querySelectorAll('input[type=checkbox]')
                .forEach(box => box.checked = state);
            this.on = state ? this.total : 0;
        },
        recount() {
            this.on = this.$refs.list.querySelectorAll(':checked').length;
        },
    }">
        <x-collapsible-section :title="$permissions->first()->groupLabel()">
            <x-slot:meta>
                <span x-text="on + '/' + total"></span>
            </x-slot:meta>

            <x-slot:actions>
                <button type="button" class="fold__link"
                        @click="toggleAll(on < total)"
                        x-text="on < total ? 'حدد الكل' : 'امسح الكل'"></button>
            </x-slot:actions>

            <div class="perms__list" x-ref="list" @change="recount()">
                @foreach ($permissions as $permission)
                    <label class="perms__item" for="{{ $idPrefix }}-{{ $permission->id }}">
                        <input
                            type="checkbox"
                            id="{{ $idPrefix }}-{{ $permission->id }}"
                            name="{{ $name }}[]"
                            value="{{ $permission->id }}"
                            class="checkbox"
                            @checked(in_array($permission->id, $chosen, false))
                        >
                        <span class="perms__label">
                            {{ $permission->name_ar }}
                            <span class="perms__key u-mono u-ltr">{{ $permission->key }}</span>
                            @if ($inherited !== null && in_array($permission->id, $inherited, false))
                                <span class="perms__inherited">من الدور</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </x-collapsible-section>
    </div>
@endforeach
