@extends('layouts.app')

@section('title', 'فتح تذكرة')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">فتح تذكرة</h1>
                <p class="page-subtitle">وقت الإبلاغ ورقم التذكرة بيتحطوا أوتوماتيك.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data">
            @csrf
            @include('tickets.partials._form', ['ticket' => null])
        </form>
    </div>
@endsection
