@extends('layouts.app')

@section('title', __('الشركات'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('الشركات') }}</h1>
                <p class="page-subtitle">{{ trans_choice(':count شركة.|شركة واحدة.|شركتان.|:count شركات.|:count شركة.|:count شركة.', $companies->total()) }}</p>
            </div>
            <div class="page__actions">
                <x-export-button route="export.companies" />
                <x-button variant="ghost" :href="route('admin.contacts.index')">{{ __('جهات الاتصال') }}</x-button>
                <x-button variant="ghost" :href="route('admin.import.index')">{{ __('استيراد من Excel') }}</x-button>
                <x-button variant="primary" :href="route('admin.companies.create')">{{ __('شركة جديدة') }}</x-button>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        @include('admin.companies.partials._filters', ['filters' => $filters])

        {{-- Genuinely empty — day one, no company entered yet — reads very
             differently from "no results for this filter", and only the
             first one deserves a screen that spends words on what to do
             next (blank.css). A filtered-to-zero result stays a plain
             table__empty row below. --}}
        @if ($companies->total() === 0 && ! array_filter($filters))
            <x-card>
                <div class="blank">
                    <p class="blank__title">{{ __('لا توجد شركات بعد.') }}</p>
                    <p class="blank__body">
                        {{ __('أضف أول شركة يدويًا، أو استورد ملفًا يحتوي على جميع شركاتك دفعة واحدة.') }}
                    </p>
                    <div class="blank__actions">
                        <x-button variant="primary" :href="route('admin.companies.create')">{{ __('شركة جديدة') }}</x-button>
                        <x-button variant="ghost" :href="route('admin.import.index')">{{ __('استيراد من Excel') }}</x-button>
                    </div>
                </div>
            </x-card>
        @else
            <x-card flush>
                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th>{{ __('الشركة') }}</th>
                                <th>{{ __('الكود') }}</th>
                                <th>{{ __('جهات الاتصال') }}</th>
                                <th>{{ __('الحالة') }}</th>
                                <th class="table__cell--actions">{{ __('إجراءات') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($companies as $company)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.companies.show', $company) }}">{{ $company->name }}</a>
                                    </td>
                                    <td class="u-mono u-ltr">{{ $company->code }}</td>
                                    <td class="u-nums">{{ $company->contacts_count }}</td>
                                    <td>
                                        @if ($company->is_active)
                                            <x-badge variant="resolved" dot>{{ __('مفعّلة') }}</x-badge>
                                        @else
                                            <x-badge variant="neutral" dot>{{ __('موقوفة') }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="table__cell--actions">
                                        <div class="row">
                                            <x-button variant="ghost" size="sm" :href="route('admin.companies.show', $company)">
                                                {{ __('جهات الاتصال') }}
                                            </x-button>
                                            <x-button variant="ghost" size="sm" :href="route('admin.companies.edit', $company)">{{ __('تعديل') }}</x-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="table__empty">
                                    <td colspan="5">{{ __('لا توجد نتائج لعوامل التصفية هذه.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{ $companies->links() }}
        @endif
    </div>
@endsection
