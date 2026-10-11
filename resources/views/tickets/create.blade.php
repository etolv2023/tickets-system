@extends('layouts.app')

@section('title', __('فتح تذكرة'))

@section('content')
    <div class="page page--ticket-workspace">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('فتح تذكرة') }}</h1>
                <p class="page-subtitle">{{ __('يُضاف وقت الإبلاغ ورقم التذكرة تلقائيًا.') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data">
            @csrf
            @include('tickets.partials._form', ['ticket' => null])
        </form>
    </div>
@endsection
