@props([
    'name' => 'attachments',
    /* The portal allows a different set from the staff app — video only when
       the virus scanner is actually running — so both the accept list and the
       line describing it are passed in rather than hardcoded here. */
    'accept' => 'image/jpeg,image/png,image/gif,image/webp,application/pdf,video/mp4,video/webm,video/quicktime',
    'hint' => null,
])

<div
    x-data="uploader()"
    class="uploader"
    @dragover.prevent="dragging = true"
    @dragleave.prevent="dragging = false"
    @drop.prevent="drop($event)"
>
    <label class="uploader__drop" :class="{ 'uploader__drop--dragging': dragging }">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 3a1 1 0 011 1v6.6l2.3-2.3a1 1 0 011.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L9 10.6V4a1 1 0 011-1z" />
            <path d="M3 13a1 1 0 011 1v2h12v-2a1 1 0 112 0v3a1 1 0 01-1 1H3a1 1 0 01-1-1v-3a1 1 0 011-1z" />
        </svg>
        <span>اسحب الملفات هنا، أو الصق صورة من الكليب بورد، أو اضغط للاختيار</span>
        <span class="uploader__hint">{{ $hint ?? 'صور أو PDF · 5 ميجا للملف · فيديو حتى 200 ميجا' }}</span>

        <input
            type="file"
            name="{{ $name }}[]"
            x-ref="input"
            class="uploader__input"
            multiple
            accept="{{ $accept }}"
            @change="pick($event)"
        >
    </label>

    {{-- One token per file, in the same order the file input carries them, so
         the server can tell which upload is the image the description points at.
         Empty for a file picked normally — only a paste into the editor makes
         one. --}}
    <template x-for="(item, index) in files" :key="`token-${index}`">
        <input type="hidden" name="attachment_tokens[]" :value="item.token ?? ''">
    </template>

    <p class="field__error" x-show="error" x-text="error" x-cloak></p>

    <div class="uploader__list" x-show="files.length" x-cloak>
        <template x-for="(item, index) in files" :key="index">
            <div class="uploader__item">
                <template x-if="item.preview">
                    <img class="uploader__thumb" :src="item.preview" alt="">
                </template>
                <template x-if="!item.preview">
                    <span class="uploader__thumb"></span>
                </template>

                <div class="uploader__meta">
                    <div class="uploader__name" x-text="item.name"></div>
                    <div class="uploader__size" x-text="item.size"></div>
                </div>

                <button type="button" class="uploader__remove" @click="remove(index)" aria-label="شيل الملف">
                    <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.3 6.3a1 1 0 011.4 0L10 8.6l2.3-2.3a1 1 0 111.4 1.4L11.4 10l2.3 2.3a1 1 0 01-1.4 1.4L10 11.4l-2.3 2.3a1 1 0 01-1.4-1.4L8.6 10 6.3 7.7a1 1 0 010-1.4z" />
                    </svg>
                </button>
            </div>
        </template>
    </div>

    @error($name)
        <p class="field__error">{{ $message }}</p>
    @enderror
    @error($name . '.*')
        <p class="field__error">{{ $message }}</p>
    @enderror
</div>
