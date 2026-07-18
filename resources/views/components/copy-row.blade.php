@props([
    'label',
    'value',
    /* A secret is shown in full — it is only ever rendered once, immediately
       after generation — but it gets its own emphasis so it is not mistaken
       for the link above it. */
    'secret' => false,
])

{{--
    A labelled value with a copy button. The value is truncated visually, never
    in what gets copied, so a long portal URL does not take three lines just to
    be copied in one click.
--}}

<div class="copy-row" x-data="copyValue(@js($value))">
    <span class="copy-row__label">{{ $label }}</span>

    <div class="copy-row__body">
        <span @class(['copy-row__value', 'u-mono', 'u-ltr', 'copy-row__value--secret' => $secret])
              title="{{ $value }}">{{ $value }}</span>

        <button type="button" class="copy-row__btn" @click="copy()"
                :title="copied ? 'اتنسخ' : 'انسخ'"
                :aria-label="copied ? 'اتنسخ' : 'انسخ'">
            <template x-if="!copied"><x-icon name="copy" size="0.95em" /></template>
            <template x-if="copied"><x-icon name="check" size="0.95em" /></template>
        </button>
    </div>
</div>
