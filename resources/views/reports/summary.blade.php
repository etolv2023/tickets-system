@extends('layouts.app')
@section('title', 'ملخص التذاكر')
@section('content')
<div class="page page--wide reports-page">
    <div class="page__head"><div><h1 class="page-title">ملخص التذاكر</h1><p class="page-subtitle">إجماليات حسب النوع أو الحالة أو الأولوية أو الموديول.</p></div></div>
    @include('reports.partials._management-filters')
    @include('reports.partials._summary-table')
</div>
@endsection
