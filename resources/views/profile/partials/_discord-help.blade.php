{{-- The one instruction everybody needs and nobody remembers. Inline rather
     than in a tooltip: it is four steps, it is needed exactly once, and a
     person who cannot find the id simply leaves the field empty and never
     gets the notification. --}}
<details class="help-note">
    <summary class="help-note__summary">{{ __('من أين أحصل على هذا المعرّف؟') }}</summary>

    <div class="help-note__body">
        <p><strong>{{ __('مهم:') }}</strong> {{ __('إنه رقم، وليس اسم المستخدم. اسم المستخدم يُكتب نصًا عاديًا ولا يُرسل إشعارًا.') }}</p>

        <ol>
            <li>{{ __('فتح Discord ←') }} <span class="u-ltr u-mono">Settings</span> {{ __('(رمز الترس بجوار اسمك)') }}</li>
            <li><span class="u-ltr u-mono">Advanced</span> {{ __('← فعّل') }} <span class="u-ltr u-mono">Developer Mode</span></li>
            <li>{{ __('عُد إلى أي خادم، وانقر بزر الفأرة الأيمن على اسمك أو صورتك') }}</li>
            <li>{{ __('اختر') }} <span class="u-ltr u-mono">Copy User ID</span> {{ __('— والصقه أعلاه') }}</li>
        </ol>

        <p class="u-subtle">
            {{ __('على الهاتف المحمول:') }} <span class="u-ltr u-mono">Settings → Behavior → Developer Mode</span>{{ __('، ثم اضغط مطولًا على اسمك واختر') }} <span class="u-ltr u-mono">Copy User ID</span>.
        </p>
    </div>
</details>
