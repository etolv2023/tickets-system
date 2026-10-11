{{-- Adding a fifth repository. Folded closed: the four that matter ship with
     the system, so this is the rare case rather than the first thing on the
     screen. --}}

<x-collapsible-card :title="__('ضيف ريبو')">
    <form method="POST" action="{{ route('admin.github.store') }}" class="form-grid">
        @csrf

        <x-field name="name" :label="__('الاسم')" required :hint="__('الاسم اللي هيظهر جنب البرانش في التذكرة.')" />

        <x-field name="owner" :label="__('المالك على جيت هب')" required
                 :hint="__('الجزء اللي قبل الشرطة في العنوان — زي etolv2023.')"
                 class="u-mono u-ltr" dir="ltr" />

        <x-field name="repo" :label="__('اسم الريبو')" required
                 :hint="__('الجزء اللي بعد الشرطة — زي trioapi.')"
                 class="u-mono u-ltr" dir="ltr" />

        <x-field name="side" :label="__('الجانب')">
            <select id="side" name="side" class="select">
                <option value="">{{ __('— من غير جانب —') }}</option>
                @foreach ($sides as $value => $label)
                    <option value="{{ $value }}" @selected(old('side') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="default_branch" :label="__('الفرع الافتراضي')" required value="main"
                 class="u-mono u-ltr" dir="ltr" />

        <div class="form-actions">
            <x-button variant="primary">{{ __('أضف') }}</x-button>
        </div>
    </form>
</x-collapsible-card>
