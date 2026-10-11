@extends('layouts.app')

@section('title', __('النسخ الاحتياطي'))

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('النسخ الاحتياطي والاستعادة') }}</h1>
                <p class="page-subtitle">
                    {{ __('نسخة كاملة من النظام — قاعدة البيانات والمرفقات والشعار — دون الكود.') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card :title="__('نسخة احتياطية')">
            <p class="field__hint">
                {{ __('ينزّل ملف zip يحتوي على النظام بالكامل الآن. هذا الملف نفسه هو ما ترفعه عند الاستعادة إذا أردت العودة إلى هذه اللحظة.') }}
            </p>

            <div class="form-actions">
                <x-button variant="primary" :href="route('admin.backup.download')">
                    <x-icon name="download" class="btn__icon" />
                    {{ __('تنزيل نسخة احتياطية الآن') }}
                </x-button>
            </div>
        </x-card>

        <x-card :title="__('استعادة')">
            {{-- x-cloak: a validation error must be able to force this open on
                 reload (a failed restore lands you back here with the form
                 hidden otherwise), but it must not flash open before Alpine
                 has read that state. --}}
            <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
                <div class="alert alert--warning">
                    <x-icon name="alert" class="alert__icon" />
                    <div>
                        <strong>{{ __('الاستعادة تحذف كل ما هو موجود حاليًا') }}</strong> {{ __('— يُحذف كل ما أُضيف بعد تاريخ النسخة الاحتياطية من تذاكر ومستخدمين وملفات، دون إمكانية للتراجع. تعيد الاستعادة النظام تمامًا إلى لحظة إنشاء النسخة الاحتياطية.') }}
                    </div>
                </div>

                <x-button variant="danger" type="button" @click="open = true">
                    {{ __('ابدأ الاستعادة') }}
                </x-button>

                <div class="modal" x-show="open" x-cloak @click="open = false"
                     @keydown.escape.window="open = false"
                     role="dialog" aria-modal="true" aria-labelledby="restore-modal-title">
                    <div class="modal__panel" @click.stop>
                        <form method="POST" action="{{ route('admin.backup.restore') }}" enctype="multipart/form-data">
                            @csrf

                            <header class="modal__header">
                                <h2 class="modal__title" id="restore-modal-title">{{ __('تأكيد الاستعادة') }}</h2>
                                <button type="button" class="modal__close" @click="open = false" aria-label="{{ __('إغلاق') }}">
                                    <x-icon name="close" size="0.85em" />
                                </button>
                            </header>

                            <div class="modal__body form-stack">
                                <x-field name="file" :label="__('ملف النسخة الاحتياطية (zip)')" type="file" required
                                         accept=".zip" />

                                <x-field name="confirm" :label="__('اكتب «:phrase» للتأكيد', ['phrase' => $confirmPhrase])"
                                         required autocomplete="off" />
                            </div>

                            <footer class="modal__footer">
                                <x-button variant="danger" size="sm">{{ __('أنا متأكد — ابدأ الاستعادة') }}</x-button>
                                <x-button variant="ghost" size="sm" type="button" @click="open = false">{{ __('إلغاء') }}</x-button>
                            </footer>
                        </form>
                    </div>
                </div>
            </div>
        </x-card>
    </div>
@endsection
