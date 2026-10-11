<x-collapsible-card :title="__('اللابلز والمتابعة')">
    <div class="stack stack--tight">
        @if ($ticket->labels->isNotEmpty())
            <div class="row row--wrap">
                @foreach ($ticket->labels as $label)
                    <x-badge :variant="$label->color">{{ $label->label() }}</x-badge>
                @endforeach
            </div>
        @endif

        @can('update', $ticket)
            @if ($labels->isNotEmpty())
                <form method="POST" action="{{ route('tickets.labels.sync', $ticket) }}"
                      x-data="{ open: false }" class="stack stack--tight">
                    @csrf
                    @method('PUT')

                    <x-button variant="ghost" size="sm" type="button" @click="open = !open">
                        {{ __('عدّل اللابلز') }}
                    </x-button>

                    <div x-show="open" x-cloak class="stack stack--tight">
                        @foreach ($labels as $label)
                            <label class="checkbox-row">
                                <input type="checkbox" name="labels[]" value="{{ $label->id }}" class="checkbox"
                                       @checked($ticket->labels->contains('id', $label->id))>
                                <span class="checkbox-row__label">
                                    <x-badge :variant="$label->color">{{ $label->label() }}</x-badge>
                                </span>
                            </label>
                        @endforeach

                        <x-button variant="primary" size="sm" block>{{ __('احفظ') }}</x-button>
                    </div>
                </form>
            @endif
        @endcan

        <hr class="divider">

        {{-- F11: assignees, tester and reporter are watchers automatically; this
             is for everyone else who wants to follow along. --}}
        <form method="POST" action="{{ route('tickets.watch', $ticket) }}">
            @csrf
            <x-button :variant="$isWatching ? 'secondary' : 'ghost'" size="sm" block>
                {{ $isWatching ? __('بطّل المتابعة') : __('تابع التذكرة') }}
            </x-button>
        </form>

        <p class="field__hint">
            {{ __(':count متابع صريح. المسؤولين والتيستر والي فتحها بياخدوا إشعارات أوتوماتيك.', ['count' => $ticket->watchers->count()]) }}
        </p>
    </div>
</x-collapsible-card>
