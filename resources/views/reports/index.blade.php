@extends('layouts.app')

@section('title', 'التقارير')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">التقارير</h1>
                <p class="page-subtitle">الأرقام مكانها هنا — بتروحلها بقصد.</p>
            </div>
            <div class="page__actions">
                <x-button variant="secondary" :href="route('export.tickets')">
                    <x-icon name="download" class="btn__icon" />
                    تصدير Excel
                </x-button>
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
