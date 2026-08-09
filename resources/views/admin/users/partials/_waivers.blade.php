{{-- ★ (2026-08-05) Who this person is allowed to keep waiting.

     A ticket cannot close while an assigned work-logging role still has an open
     work log — that is the default and it is the right one. This is the named
     exception, and it is a PAIR rather than a flag: being a bottleneck on one
     colleague's tickets is not the same as being one on everybody's, and a
     blanket exemption would quietly stop this person's «خلصت» meaning anything
     at all.

     Edit only, like the permissions card above it: on create there is nobody to
     pair with yet. --}}
<x-card title="الإعفاء من «خلصت»">
    <x-slot:actions>
        <span class="u-subtle">مع مين مش لازم يخلّص</span>
    </x-slot:actions>

    <div class="form-stack" x-data="{ all: @js((bool) old('waiver_all', $waiverAll)) }">
        {{-- An unticked checkbox and an empty multi-select both post nothing, so
             without this marker "cleared everything" is indistinguishable from
             "this form never had the card". --}}
        <input type="hidden" name="waivers_present" value="1">

        <p class="field__hint">
            التذكرة مبتتقفلش لحد ما كل جهة تضغط «خلصت». هنا بتقول إن {{ $user->name }}
            <strong>مش ملزم</strong> يضغطها — بس على التذاكر اللي فيها صب تاسك
            لواحد من اللي هتختارهم. زراير «بدأت/خلصت» بتفضل عنده زي ما هي،
            ونقاطه مبتتأثرش.
        </p>

        <label class="checkbox-row">
            <input type="checkbox" name="waiver_all" value="1" class="checkbox" x-model="all">
            <span class="checkbox-row__label">
                مع الكل
                <small>مش ملزم يضغط «خلصت» على أي تذكرة، مهما كان اللي معاه فيها.</small>
            </span>
        </label>

        <div x-show="! all" x-cloak>
            <div class="field">
                <label class="field__label">مع الأشخاص دول بس</label>

                {{-- A plain multi-select, not a combobox: the list is the team,
                     it is short, and seeing every name at once is the point —
                     you are choosing who this person may keep waiting. --}}
                <select name="waivers[]" class="select" multiple size="8">
                    @foreach ($waiverCandidates as $candidate)
                        <option value="{{ $candidate->id }}"
                                @selected(in_array($candidate->id, old('waivers', $waiverIds), false))>
                            {{ $candidate->name }}
                        </option>
                    @endforeach
                </select>

                <p class="field__hint">
                    امسك Ctrl (أو Cmd) عشان تختار أكتر من واحد.
                    لو التذكرة مفيهاش صب تاسك لأي حد منهم، {{ $user->name }} هيفضل بيبلوك عادي.
                </p>
            </div>
        </div>
    </div>
</x-card>
