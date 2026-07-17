@extends('layouts.app')

@section('title', 'نقاطي')

@section('content')
    <div class="page page--narrow">
        <div class="page__head">
            <div>
                <h1 class="page-title">نقاطي</h1>
                <p class="page-subtitle">النقاط بتتصرف مرة واحدة أول ما التذكرة تتحل.</p>
            </div>
            <div class="page__actions">
                <span class="points-total">{{ rtrim(rtrim(number_format($total, 2), '0'), '.') }}</span>
            </div>
        </div>

        @include('reports.partials._month-picker', ['route' => 'reports.my-points'])

        <x-card flush>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>التذكرة</th>
                            <th>النوع</th>
                            <th>الجهة</th>
                            <th>السبب</th>
                            <th class="table__cell--num">النقاط</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('tickets.show', $row->ticket_id) }}">
                                        <span class="u-mono u-ltr">{{ $row->ticket->ticket_number }}</span>
                                        {{ Str::limit($row->ticket->title, 40) }}
                                    </a>
                                </td>
                                <td><x-badge :variant="$row->ticket->type->variant()">{{ $row->ticket->type->label() }}</x-badge></td>
                                <td>{{ $row->side->label() }}</td>
                                <td class="u-subtle">{{ $row->reason }}</td>
                                <td class="table__cell--num">{{ rtrim(rtrim($row->points, '0'), '.') }}</td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="5">مأخدتش نقاط في الشهر ده.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <p class="field__hint">
            تسجيل الوقت مالوش علاقة بالنقاط — النقاط على إنجاز التذكرة مش على الساعات.
        </p>
    </div>
@endsection
