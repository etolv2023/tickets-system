@php $generated = session('generated_password'); @endphp

{{-- Shown once, right after the reset. It is never stored in readable form,
     so there is no second chance to see it. F22.3 --}}
<x-alert variant="success">
    <strong>{{ __('تم توليد كلمة مرور جديدة لـ «:name»', ['name' => $generated['user']]) }}</strong>
    <p class="field__hint">{{ __('انسخها وأرسلها إليه الآن — لن تظهر مرة أخرى، وسيُلزَم بتغييرها عند أول دخول.') }}</p>
    <p class="u-mono generated-password">{{ $generated['password'] }}</p>
</x-alert>
