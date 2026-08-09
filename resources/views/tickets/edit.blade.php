@extends('layouts.app')

@section('title', 'تعديل ' . $ticket->ticket_number)

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <span class="ticket__number">{{ $ticket->ticket_number }}</span>
                <h1 class="page-title">تعديل التذكرة</h1>
            </div>
        </div>

        {{-- enctype is not optional now that this form carries an uploader: a
             multipart form posted as urlencoded arrives with no files at all,
             silently. --}}
        <form method="POST" action="{{ route('tickets.update', $ticket) }}"
              enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('tickets.partials._form')
        </form>
    </div>
@endsection
