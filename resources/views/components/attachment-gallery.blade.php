@props(['attachments'])

@php
    $images = $attachments->filter->isImage()->values();
    $files = $attachments->reject->isImage()->values();

    // Only what the lightbox needs. Every url below is an authorised route —
    // there is no direct path to the stored file. F04.2
    $lightboxItems = $images->map(fn ($a) => [
        'src' => route('attachments.view', $a),
        'name' => $a->original_name,
        'download' => route('attachments.download', $a),
    ])->all();
@endphp

@if ($attachments->isNotEmpty())
    <div x-data="lightbox()">
        @if ($images->isNotEmpty())
            <div class="gallery">
                @foreach ($images as $index => $image)
                    <button
                        type="button"
                        class="gallery__item"
                        @click="show(@js($lightboxItems), {{ $index }})"
                        aria-label="كبّر {{ $image->original_name }}"
                    >
                        {{-- The thumbnail, not the original: a 4MB photo never
                             loads into a grid. CLAUDE.md § 4.8 --}}
                        <img
                            class="gallery__img"
                            src="{{ route('attachments.thumb', $image) }}"
                            alt="{{ $image->original_name }}"
                            loading="lazy"
                        >
                    </button>
                @endforeach
            </div>
        @endif

        @if ($files->isNotEmpty())
            <div class="stack stack--tight">
                @foreach ($files as $file)
                    <a class="gallery__file" href="{{ route('attachments.download', $file) }}">
                        <svg class="gallery__file-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4 3a1 1 0 011-1h6l5 5v10a1 1 0 01-1 1H5a1 1 0 01-1-1V3zm7 1.5V7h2.5L11 4.5z" clip-rule="evenodd" />
                        </svg>
                        <span class="uploader__meta">
                            <span class="uploader__name">{{ $file->original_name }}</span>
                            <span class="uploader__size">{{ $file->sizeLabel() }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="lightbox" x-show="open" x-cloak @keydown.escape.window="close()"
             @keydown.arrow-left.window="next()" @keydown.arrow-right.window="prev()" @click.self="close()">
            <div class="lightbox__bar">
                <span class="lightbox__name" x-text="current?.name"></span>
                <div class="row">
                    <a class="lightbox__btn" :href="current?.download" aria-label="نزّل">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 3a1 1 0 011 1v6.6l2.3-2.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L9 10.6V4a1 1 0 011-1zM3 15a1 1 0 011 1h12a1 1 0 112 0v1a1 1 0 01-1 1H3a1 1 0 01-1-1v-1a1 1 0 011-1z" />
                        </svg>
                    </a>
                    <button type="button" class="lightbox__btn" @click="close()" aria-label="اقفل">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.3 6.3a1 1 0 011.4 0L10 8.6l2.3-2.3a1 1 0 111.4 1.4L11.4 10l2.3 2.3a1 1 0 01-1.4 1.4L10 11.4l-2.3 2.3a1 1 0 01-1.4-1.4L8.6 10 6.3 7.7a1 1 0 010-1.4z" />
                        </svg>
                    </button>
                </div>
            </div>

            <template x-if="items.length > 1">
                <button type="button" class="lightbox__nav lightbox__nav--prev" @click.stop="prev()" aria-label="السابق">
                    <span class="lightbox__btn">›</span>
                </button>
            </template>

            <img class="lightbox__img" :src="current?.src" :alt="current?.name" @click.stop>

            <template x-if="items.length > 1">
                <button type="button" class="lightbox__nav lightbox__nav--next" @click.stop="next()" aria-label="التالي">
                    <span class="lightbox__btn">‹</span>
                </button>
            </template>
        </div>
    </div>
@endif
