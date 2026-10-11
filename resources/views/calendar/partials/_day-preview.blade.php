{{-- The day preview modal a month/week cell opens. Lives inside _grid's
     calendarDayPreview scope; its rows are fetched per day. --}}
<div class="cal-preview" x-show="open" x-cloak role="dialog" aria-modal="true" :aria-label="{{ Js::from(__('تفاصيل')) }} + ' ' + label">
    <button type="button" class="cal-preview__backdrop" @click="close()" aria-label="{{ __('إغلاق') }}"></button>
    <section class="cal-preview__panel">
        <header class="cal-preview__head">
            <div class="cal-preview__heading">
                <button type="button" class="btn btn--ghost btn--icon" x-show="selected" @click="back()" aria-label="{{ __('العودة إلى القائمة') }}">
                    <x-icon name="chevron-right" />
                </button>
                <div>
                    <strong x-text="selected ? selected.ticketNumber : label"></strong>
                    <span x-text="selected ? selected.kindLabel : items.length + ' ' + {{ Js::from(__('عنصر')) }}"></span>
                </div>
            </div>
            <div class="row">
                <a class="btn btn--primary btn--sm" x-show="selected" :href="selected?.url">{{ __('فتح التذكرة كاملة') }}</a>
                <a class="btn btn--secondary btn--sm"
                   :href="'{{ route($routeName) }}?view=day&date=' + date">{{ __('فتح اليوم في صفحة') }}</a>
                <button type="button" class="btn btn--ghost btn--icon" @click="close()" aria-label="{{ __('إغلاق') }}">
                    <x-icon name="close" />
                </button>
            </div>
        </header>

        <p class="cal-preview__state" x-show="loading" x-cloak>{{ __('جارٍ التحميل…') }}</p>
        <p class="cal-preview__state" x-show="failed" x-cloak>{{ __('تعذّر تحميل عناصر هذا اليوم. أغلق النافذة وافتحها للمحاولة مرة أخرى.') }}</p>

        <div class="cal-preview__body" x-show="!selected && !loading">
            <template x-for="(item, index) in items" :key="`${item.kind}-${item.ticketNumber}-${index}`">
                <button type="button" class="cal-preview__item" @click="choose(item)">
                    <span class="cal-preview__kind" :class="`cal-preview__kind--${item.kind}`" x-text="item.kindLabel"></span>
                    <span class="cal-preview__item-main">
                        <strong x-text="item.title"></strong>
                        <small><span x-text="item.ticketNumber"></span><template x-if="item.assignee"><span> · <span x-text="item.assignee"></span></span></template></small>
                    </span>
                    <x-icon name="chevron-left" />
                </button>
            </template>
        </div>

        <div class="cal-preview__detail" x-show="selected" x-cloak>
            <span class="cal-preview__kind" :class="`cal-preview__kind--${selected?.kind}`" x-text="selected?.kindLabel"></span>
            <h2 x-text="selected?.title"></h2>
            <p x-text="selected?.ticketTitle"></p>
            <dl>
                <template x-if="selected?.assignee"><div><dt>{{ __('المسؤول') }}</dt><dd x-text="selected.assignee"></dd></div></template>
                <template x-if="selected?.hours"><div><dt>{{ __('الوقت المتوقع') }}</dt><dd><span x-text="selected.hours"></span> {{ __('ساعة') }}</dd></div></template>
                <div><dt>{{ __('التوقيت') }}</dt><dd x-text="selected?.overdue ? {{ Js::from(__('متأخرة')) }} : {{ Js::from(__('في الموعد')) }}"></dd></div>
            </dl>
        </div>
    </section>
</div>
