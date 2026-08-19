{{-- F18.3 — the rate card. One form, one submit: a rate card is read across its
     rows, and saving them one at a time leaves it in states nobody meant. --}}
<x-card title="سعر النقطة لكل نوع">
    <form method="POST" action="{{ route('admin.point-values.update', request()->only('period')) }}">
        @csrf
        @method('PUT')

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>النوع</th>
                        <th>النقاط الافتراضية</th>
                        <th>سعر النقطة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $t)
                        @php $value = \App\Casts\TicketTypeValue::for($t->key); @endphp
                        <tr>
                            <td>
                                <x-badge :variant="$value->variant()" :icon="$value->icon()">{{ $t->name_ar }}</x-badge>
                                <span class="u-subtle u-mono">{{ $t->key }}</span>
                            </td>
                            <td class="u-mono">{{ (float) $t->default_points }}</td>
                            <td>
                                <div class="rate-cell">
                                    <input type="number" name="values[{{ $t->id }}]"
                                           value="{{ (float) $t->point_value }}"
                                           step="0.25" min="0" max="100000"
                                           class="input rate-cell__input"
                                           aria-label="سعر النقطة لـ {{ $t->name_ar }}">
                                    <span class="settings__unit">للنقطة</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="form-actions">
            <x-button variant="primary">احفظ الأسعار</x-button>
        </div>
    </form>
</x-card>
