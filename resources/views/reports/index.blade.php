@extends('layouts.app')

@section('title', 'التقارير')

@section('content')
    {{-- ★★★★ (2026-07-21): paired report-card grids, same case as the dashboard. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">التقارير</h1>
                <p class="page-subtitle">الأرقام مكانها هنا — بتروحلها بقصد.</p>
            </div>
            <div class="page__actions">
                <x-export-button route="export.reports" />
            </div>
        </div>

        @include('reports.partials._month-picker', ['route' => 'reports.index'])

        @include('reports.partials._distribution')
        @include('reports.partials._resolution')
        @include('reports.partials._breaches')
        @include('reports.partials._companies')
        @include('reports.partials._load')
        @include('reports.partials._time')
    </div>
@endsection
