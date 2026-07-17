{{-- Inline add: never leaves the ticket page. F08 --}}
<div x-data="{ open: {{ $errors->any() && old('title') ? 'true' : 'false' }} }" class="stack stack--tight">
    <x-button variant="secondary" size="sm" type="button" x-show="!open" @click="open = true">
        + صب تاسك جديدة
    </x-button>

    <form method="POST" action="{{ route('tickets.subtasks.store', $ticket) }}" x-show="open" x-cloak>
        @csrf
        <x-form-errors />
        @include('tickets.partials._subtask-fields', ['subtask' => null])

        <div class="form-actions">
            <x-button variant="primary" size="sm">أضف</x-button>
            <x-button variant="ghost" size="sm" type="button" @click="open = false">إلغاء</x-button>
        </div>
    </form>
</div>
