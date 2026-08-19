@extends('layouts.app')

@section('title', 'سعر النقطة والمستحقات')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">سعر النقطة والمستحقات</h1>
                <p class="page-subtitle">
                    فوق: النقطة الواحدة بتساوي كام في كل نوع تذكرة.
                    تحت: كل واحد في التيم له كام على أساس نقاطه الشهر ده.
                    <br>
                    <strong>مهم:</strong> الفلوس بتتحسب وقت العرض من السعر الحالي — مش متخزّنة.
                    يعني لو غيّرت سعر نوع، الشهور القديمة بتتحسب بالسعر الجديد.
                    <strong>وخصومات التأخير داخلة في الحساب بالسالب.</strong>
                </p>
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
