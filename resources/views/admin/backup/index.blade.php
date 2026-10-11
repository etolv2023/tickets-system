@extends('layouts.app')

@section('title', __('الباك أب'))

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('الباك أب والاسترجاع') }}</h1>
                <p class="page-subtitle">
                    {{ __('نسخة كاملة من النظام — الداتابيز والمرفقات واللوجو — من غير الكود.') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card :title="__('باك أب')">
            <p class="field__hint">
                {{ __('بيحمّل ملف zip فيه النظام كله دلوقتي. الملف ده هو نفسه اللي المفروض ترفعه في الاسترجاع لو حبيت ترجع للحظة دي تاني.') }}
            </p>

            <div class="form-actions">
                <x-button variant="primary" :href="route('admin.backup.download')">
                    <x-icon name="download" class="btn__icon" />
                    {{ __('نزّل باك أب دلوقتي') }}
                </x-button>
            </div>
        </x-card>

        <x-card :title="__('استرجاع')">
            {{-- x-cloak: a validation error must be able to force this open on
                 reload (a failed restore lands you back here with the form
                 hidden otherwise), but it must not flash open before Alpine
                 has read that state. --}}
            <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
                <div class="alert alert--warning">
                    <x-icon name="alert" class="alert__icon" />
                    <div>
                        <strong>{{ __('الاسترجاع بيمسح كل حاجة موجودة دلوقتي') }}</strong> {{ __('— كل تذكرة وكل مستخدم وكل ملف اتضاف بعد تاريخ الباك أب بيتشال، من غير رجعة. الاسترجاع بيرجّع النظام بالظبط لحظة ما الباك أب اتعمل.') }}
                    </div>
                </div>

                <x-button variant="danger" type="button" @click="open = true">
                    {{ __('ابدأ الاسترجاع') }}
                </x-button>

                <div class="modal" x-show="open" x-cloak @click="open = false"
                     @keydown.escape.window="open = false"
                     role="dialog" aria-modal="true" aria-labelledby="restore-modal-title">
                    <div class="modal__panel" @click.stop>
                        <form method="POST" action="{{ route('admin.backup.restore') }}" enctype="multipart/form-data">
                            @csrf

                            <header class="modal__header">
                                <h2 class="modal__title" id="restore-modal-title">{{ __('تأكيد الاسترجاع') }}</h2>
                                <button type="button" class="modal__close" @click="open = false" aria-label="{{ __('إغلاق') }}">
                                    <x-icon name="close" size="0.85em" />
                                </button>
                            </header>

                            <div class="modal__body form-stack">
                                <x-field name="file" :label="__('ملف الباك أب (zip)')" type="file" required
                                         accept=".zip" />

                                <x-field name="confirm" :label="__('اكتب «:phrase» عشان تأكد', ['phrase' => $confirmPhrase])"
                                         required autocomplete="off" />
                            </div>

                            <footer class="modal__footer">
                                <x-button variant="danger" size="sm">{{ __('أنا متأكد — ابدأ الاسترجاع') }}</x-button>
                                <x-button variant="ghost" size="sm" type="button" @click="open = false">{{ __('إلغاء') }}</x-button>
                            </footer>
                        </form>
                    </div>
                </div>
            </div>
        </x-card>
    </div>
@endsection
