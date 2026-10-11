@extends('layouts.app')
@section('title', __('ملخص التذاكر'))
@section('content')
<div class="page page--wide reports-page">
    <div class="page__head"><div><h1 class="page-title">{{ __('ملخص التذاكر') }}</h1><p class="page-subtitle">{{ __('إجماليات حسب النوع أو الحالة أو الأولوية أو الوحدة.') }}</p></div><x-export-button route="export.summary" /></div>
    @include('reports.partials._management-filters')
    @include('reports.partials._summary-table')
</div>
@endsection
