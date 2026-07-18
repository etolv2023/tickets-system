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

        <form method="POST" action="{{ route('tickets.update', $ticket) }}">
            @csrf
            @method('PUT')
            @include('tickets.partials._form')
        </form>
    </div>
@endsection
