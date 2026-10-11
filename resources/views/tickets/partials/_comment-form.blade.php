<form method="POST" action="{{ route('tickets.comments.store', $ticket) }}"
      enctype="multipart/form-data" class="form-stack">
    @csrf

    <div class="field">
        <label class="field__label" for="body">{{ __('تعليق جديد') }}</label>
        <x-editor name="body" :placeholder="__('اكتب تعليق. تقدر تلصق صور.')" />
    </div>

    <x-uploader name="attachments" />

    <div class="row row--between row--wrap">
        @can('commentInternally', $ticket)
            <label class="checkbox-row">
                <input type="checkbox" name="is_internal" value="1" class="checkbox">
                <span class="checkbox-row__label">
                    {{ __('تعليق داخلي') }}
                    <small>{{ __('مايتشوفش لو الخيط اتصدّر للعميل بعدين.') }}</small>
                </span>
            </label>
        @else
            <span></span>
        @endcan

        <x-button variant="primary">{{ __('أضف التعليق') }}</x-button>
    </div>
</form>
