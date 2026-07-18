{{-- Adding a subtask never leaves the ticket page. The form lives in a modal
     rather than expanding the card, because it is eight fields deep and pushing
     the timeline down the page loses your place. F08 --}}
<div x-data="{ open: {{ $errors->any() && old('title') ? 'true' : 'false' }} }">
    <x-button variant="secondary" size="sm" type="button" @click="open = true">
        <x-icon name="plus" class="btn__icon" />
        صب تاسك جديدة
    </x-button>

    {{-- Backdrop closes; the panel stops the click so a stray click inside a
         half-filled form doesn't throw the work away. Escape closes too. --}}
    <div class="modal" x-show="open" x-cloak @click="open = false"
         @keydown.escape.window="open = false"
         role="dialog" aria-modal="true" aria-labelledby="subtask-modal-title">
        <div class="modal__panel" @click.stop>
            <header class="modal__header">
                <h2 class="modal__title" id="subtask-modal-title">صب تاسك جديدة</h2>
                <button type="button" class="modal__close" @click="open = false" aria-label="إغلاق">
                    <x-icon name="close" size="0.85em" />
                </button>
            </header>

            <form method="POST" action="{{ route('tickets.subtasks.store', $ticket) }}">
                @csrf

                <div class="modal__body">
                    <x-form-errors />
                    @include('tickets.partials._subtask-fields', ['subtask' => null])
                </div>

                <footer class="modal__footer">
                    <x-button variant="primary" size="sm">أضف</x-button>
                    <x-button variant="ghost" size="sm" type="button" @click="open = false">إلغاء</x-button>
                </footer>
            </form>
        </div>
    </div>
</div>
