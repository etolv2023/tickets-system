@extends('layouts.app')

@section('title', 'مصفوفة النقاط')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">مصفوفة النقاط</h1>
                <p class="page-subtitle">
                    النقاط بتتصرف مرة واحدة أول ما التذكرة تتحل.
                </p>
            </div>
        </div>

        {{-- The one thing an admin must understand before touching this. F18 --}}
        <x-alert>
            <strong>التعديل هنا مبيأثرش بأثر رجعي.</strong>
            التذاكر الي اتحلت خلاص بتحتفظ بنقاطها زي ما هي — التغيير بيسري على الي هيتحل من دلوقتي بس.
            <br>
            الخانة الفاضية معناها إن الجهة دي مبتاخدش نقط على التركيبة دي.
        </x-alert>

        <div x-data="pointMatrix()">
            <p class="field__error" x-show="error" x-text="error" x-cloak></p>
            <p class="u-subtle" x-show="saved" x-text="saved" x-cloak></p>

            <x-card flush>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>النوع</th>
                                <th>النطاق</th>
                                @foreach ($sides as $side)
                                    <th class="matrix__role-head">{{ $side->label() }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        <x-badge :variant="$row['type']->variant()">{{ $row['type']->label() }}</x-badge>
                                    </td>
                                    <td>{{ $scopeLabels[$row['scope']] }}</td>

                                    @foreach ($sides as $side)
                                        @php
                                            $key = "{$row['type']->value}|{$row['scope']}|{$side->value}";
                                            $rule = $rules[$key] ?? null;
                                        @endphp
                                        <td class="matrix__cell">
                                            @include('admin.point-rules.partials._cell', [
                                                'rule' => $rule,
                                                'type' => $row['type']->value,
                                                'scope' => $row['scope'],
                                                'side' => $side->value,
                                            ])
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>
@endsection
