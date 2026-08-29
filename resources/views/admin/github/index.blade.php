@extends('layouts.app')

@section('title', 'جيت هب')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">جيت هب</h1>
                <p class="page-subtitle">
                    الريبوز اللي النظام بيدوّر فيها على برانشات التذاكر. الاتصال <strong>قراءة بس</strong> —
                    النظام مبيعملش برانش ولا PR ولا بيحذف حاجة، والتوكن نفسه المفروض يكون
                    <span class="u-mono u-ltr">Contents: Read-only</span> +
                    <span class="u-mono u-ltr">Pull requests: Read-only</span>.
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        @unless ($connected)
            <x-alert variant="error">
                التكامل مقفول. حط <span class="u-mono u-ltr">GITHUB_ENABLED=true</span> و
                <span class="u-mono u-ltr">GITHUB_TOKEN</span> في ملف <span class="u-mono u-ltr">.env</span>،
                وبعدها شغّل <span class="u-mono u-ltr">php artisan github:check</span>.
            </x-alert>
        @endunless

        {{-- Not verified in the browser on purpose: checking the token and its
             scopes is four API calls, and a page with a 300ms budget does not
             make network requests to another company's server while it renders. --}}
        <div class="note">
            للتأكد من التوكن وصلاحياته: <span class="u-mono u-ltr">php artisan github:check</span> —
            وده كمان بيحذّرك لو التوكن معاه صلاحية كتابة مش محتاجينها، أو قرّب يخلص.
        </div>

        {{-- Cards, not a table: six editable fields per repository do not fit a
             row, and there are four rows. See _repo-card for the full reasoning. --}}
        <div class="stack">
            @foreach ($repositories as $repository)
                @include('admin.github.partials._repo-card', ['repository' => $repository])
            @endforeach
        </div>

        @if ($unmatchedPulls > 0)
            <div class="note">
                فيه {{ $unmatchedPulls }} PR الهيد برانش بتاعهم مش فيه رقم تذكرة معروف —
                غالباً غلطة في اسم البرانش.
            </div>
        @endif

        @include('admin.github.partials._new-repo')
    </div>
@endsection
