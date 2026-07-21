{{-- Mounted once in layouts/app.blade.php. $store.toast (app.js) is the only
     way anything reaches this — a component fires a DOM event, app.js turns
     it into a push(). --}}
<div class="toast-host" x-data aria-live="polite" aria-atomic="true">
    <template x-for="item in $store.toast.items" :key="item.id">
        <div class="toast" :class="`toast--${item.variant}`" x-show="true" x-transition role="status">
            <span class="toast__body" x-text="item.message"></span>
            <button type="button" class="toast__close" @click="$store.toast.dismiss(item.id)" aria-label="اقفل الرسالة">
                <x-icon name="close" size="0.85em" />
            </button>
        </div>
    </template>
</div>
