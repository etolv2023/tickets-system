@php $generated = session('generated_password'); @endphp

{{-- Shown once, right after the reset. It is never stored in readable form,
     so there is no second chance to see it. F22.3 --}}
<x-alert variant="success">
    <strong>{{ __('اتولّدت كلمة سر جديدة لـ «:name»', ['name' => $generated['user']]) }}</strong>
    <p class="field__hint">{{ __('انسخها وابعتها له دلوقتي — مش هتظهر تاني، وهو هيتجبر يغيّرها أول دخول.') }}</p>
    <p class="u-mono generated-password">{{ $generated['password'] }}</p>
</x-alert>
