@extends('layouts.app')

@section('title', __('التقارير'))

@section('content')
    {{-- ★★★★ (2026-07-21): paired report-card grids, same case as the dashboard. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('التقارير') }}</h1>
                <p class="page-subtitle">{{ __('مكان الأرقام هنا — تصل إليها عن قصد.') }}</p>
            </div>
            <div class="page__actions">
                <x-export-button route="export.reports" />
            </div>
        </div>

        @include('reports.partials._report-filters')

        @include('reports.partials._distribution')
        @include('reports.partials._resolution')
        @include('reports.partials._breaches')
        @include('reports.partials._companies')
        @include('reports.partials._load')
        @include('reports.partials._time')
    </div>
@endsection
