{{-- Adding a fifth repository. Folded closed: the four that matter ship with
     the system, so this is the rare case rather than the first thing on the
     screen. --}}

<x-collapsible-card :title="__('إضافة مستودع')">
    <form method="POST" action="{{ route('admin.github.store') }}" class="form-grid">
        @csrf

        <x-field name="name" :label="__('الاسم')" required :hint="__('الاسم الذي يظهر بجوار الفرع في التذكرة.')" />

        <x-field name="owner" :label="__('المالك في GitHub')" required
                 :hint="__('الجزء الذي قبل الشرطة المائلة في العنوان — مثل etolv2023.')"
                 class="u-mono u-ltr" dir="ltr" />

        <x-field name="repo" :label="__('اسم المستودع')" required
                 :hint="__('الجزء الذي بعد الشرطة المائلة — مثل trioapi.')"
                 class="u-mono u-ltr" dir="ltr" />

        <x-field name="side" :label="__('الجانب')">
            <select id="side" name="side" class="select">
                <option value="">{{ __('— دون جهة —') }}</option>
                @foreach ($sides as $value => $label)
                    <option value="{{ $value }}" @selected(old('side') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="default_branch" :label="__('الفرع الافتراضي')" required value="main"
                 class="u-mono u-ltr" dir="ltr" />

        <div class="form-actions">
            <x-button variant="primary">{{ __('إضافة') }}</x-button>
        </div>
    </form>
</x-collapsible-card>
