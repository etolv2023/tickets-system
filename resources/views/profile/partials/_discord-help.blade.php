{{-- The one instruction everybody needs and nobody remembers. Inline rather
     than in a tooltip: it is four steps, it is needed exactly once, and a
     person who cannot find the id simply leaves the field empty and never
     gets the notification. --}}
<details class="help-note">
    <summary class="help-note__summary">{{ __('أجيب الـ ID ده منين؟') }}</summary>

    <div class="help-note__body">
        <p><strong>{{ __('مهم:') }}</strong> {{ __('ده رقم، مش اسم المستخدم. اسم المستخدم بيتكتب نص عادي ومبيعملش إشعار.') }}</p>

        <ol>
            <li>{{ __('افتح ديسكورد →') }} <span class="u-ltr u-mono">Settings</span> {{ __('(الترس جنب اسمك)') }}</li>
            <li><span class="u-ltr u-mono">Advanced</span> {{ __('→ شغّل') }} <span class="u-ltr u-mono">Developer Mode</span></li>
            <li>{{ __('ارجع لأي سيرفر، كليك يمين على اسمك أو صورتك') }}</li>
            <li>{{ __('اختار') }} <span class="u-ltr u-mono">Copy User ID</span> {{ __('— والصقه فوق') }}</li>
        </ol>

        <p class="u-subtle">
            {{ __('على الموبايل:') }} <span class="u-ltr u-mono">Settings → Behavior → Developer Mode</span>{{ __('، وبعدين اضغط مطوّل على اسمك واختار') }} <span class="u-ltr u-mono">Copy User ID</span>.
        </p>
    </div>
</details>
