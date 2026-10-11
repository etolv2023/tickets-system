@extends('layouts.app')

@section('title', __('سعر النقطة والمستحقات'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('سعر النقطة والمستحقات') }}</h1>
                <p class="page-subtitle">
                    {{ __('أعلى: قيمة النقطة الواحدة لكل نوع تذكرة. أدناه: مستحقات كل عضو في الفريق بناءً على نقاطه هذا الشهر.') }}
                    <br>
                    <strong>{{ __('مهم:') }}</strong> {{ __('تُحسب المبالغ وقت العرض من السعر الحالي — ولا تُخزَّن. أي إذا غيّرت سعر نوع، تُحسب الأشهر السابقة بالسعر الجديد.') }}
                    <strong>{{ __('وتدخل خصومات التأخير في الحساب بالسالب.') }}</strong>
                </p>
            </div>
            {{-- The file carries the rate card and the per-type split next to
                 each person's figure: these are salary numbers, and the sheet
                 has to show its working the way the screen does. --}}
            <div class="page__actions">
                <x-export-button route="export.money" />
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        @include('admin.point-values.partials._rates')

        @include('admin.point-values.partials._report')
    </div>
@endsection
