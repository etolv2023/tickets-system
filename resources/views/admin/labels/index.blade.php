@extends('layouts.app')

@section('title', 'اللابلز')

@section('content')
    <div class="page page--narrow">
        <div class="page__head">
            <div>
                <h1 class="page-title">اللابلز</h1>
                <p class="page-subtitle">الألوان من الباليتة الدلالية بس — مفيش لون عشوائي.</p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card title="لابل جديد">
            <form method="POST" action="{{ route('admin.labels.store') }}" class="form-grid">
                @csrf
                <x-field name="name" label="الاسم" required />
                <x-field name="color" label="اللون" required>
                    <select id="color" name="color" class="select" required>
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <div class="form-actions">
                    <x-button variant="primary">أضف</x-button>
                </div>
            </form>
        </x-card>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>اللابل</th>
                            <th>اللون</th>
                            <th>التذاكر</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($labels as $label)
                            <tr>
                                <td><x-badge :variant="$label->color">{{ $label->name }}</x-badge></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.labels.update', $label) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $label->name }}">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $colorLabel)
                                                <option value="{{ $value }}" @selected($label->color === $value)>{{ $colorLabel }}</option>
                                            @endforeach
                                        </select>
                                        <x-button variant="ghost" size="sm">غيّر</x-button>
                                    </form>
                                </td>
                                <td class="u-nums">{{ $label->tickets_count }}</td>
                                <td class="table__cell--actions">
                                    <form method="POST" action="{{ route('admin.labels.destroy', $label) }}"
                                          onsubmit="return confirm('هيتشال من {{ $label->tickets_count }} تذكرة. متأكد؟')">
                                        @csrf
                                        @method('DELETE')
                                        <x-button variant="ghost" size="sm">حذف</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="4">مفيش لابلز.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
