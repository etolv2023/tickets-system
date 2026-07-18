@extends('layouts.app')

@section('title', 'سجل التدقيق')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">سجل التدقيق</h1>
                <p class="page-subtitle">
                    للقراءة بس — مفيش تعديل ولا حذف. سجل بيتعدّل مش سجل.
                </p>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.audit.index') }}" class="filters">
            <div class="filters__combobox">
                <x-combobox name="user" resource="users"
                            :value="$filters['user'] ?? null"
                            :selected="$selectedUser ?? null"
                            placeholder="كل المستخدمين" />
            </div>

            <select name="action" class="select filters__select">
                <option value="">كل الأفعال</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                @endforeach
            </select>

            <select name="subject" class="select filters__select">
                <option value="">كل الكائنات</option>
                @foreach ($subjects as $class => $label)
                    <option value="{{ $label }}" @selected(($filters['subject'] ?? '') === $label)>{{ $label }}</option>
                @endforeach
            </select>

            <div class="filters__group">
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input" aria-label="من">
                <span class="u-subtle" aria-hidden="true">—</span>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input" aria-label="لـ">
            </div>

            <x-button variant="secondary">فلترة</x-button>

            @if (array_filter($filters))
                <x-button variant="ghost" :href="route('admin.audit.index')">مسح</x-button>
            @endif
        </form>

        <x-card flush>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الوقت</th>
                            <th>مين</th>
                            <th>الفعل</th>
                            <th>الكائن</th>
                            <th>قبل / بعد</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="u-nums">
                                    {{ $log->created_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}
                                </td>
                                <td>
                                    @if ($log->user)
                                        <div class="row">
                                            <x-avatar :user="$log->user" size="sm" />
                                            {{ $log->user->name }}
                                        </div>
                                    @else
                                        <span class="u-subtle">النظام</span>
                                    @endif
                                </td>
                                <td class="u-mono u-ltr">{{ $log->action }}</td>
                                <td>
                                    @if ($log->subject_type)
                                        <span class="u-subtle">{{ class_basename($log->subject_type) }}</span>
                                        <span class="u-mono">#{{ $log->subject_id }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($log->changes)
                                        <details>
                                            <summary class="u-subtle">اعرض</summary>
                                            <pre class="install__log">{{ json_encode($log->changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="u-mono u-ltr u-subtle">{{ $log->ip }}</td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="6">مفيش سجلات بالفلاتر دي.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $logs->links() }}
    </div>
@endsection
