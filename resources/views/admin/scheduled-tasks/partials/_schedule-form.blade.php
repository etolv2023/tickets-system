{{-- When the task runs, and whether it runs at all.

     The dropdowns cover every schedule these tasks realistically need; «متقدم»
     reveals a raw cron field for the ones they do not. Which sub-fields matter
     depends on the frequency, so Alpine shows and hides them — a form that asks
     for a weekday on an hourly task is asking a question with no answer.

     Field ids carry the task id: several of these render on one page, and two
     <label for="hour"> would both point at the first one. --}}

<form method="POST" action="{{ route('admin.scheduled-tasks.update', $task) }}"
      x-data="{ frequency: @js($parts['frequency']) }" class="form-stack">
    @csrf
    @method('PUT')

    <hr class="divider">

    <div class="form-grid">
        <x-field :name="'frequency_' . $task->id" label="التكرار" required>
            <select id="frequency_{{ $task->id }}" name="frequency" class="select" x-model="frequency" required>
                @foreach ($frequencies as $value => $label)
                    <option value="{{ $value }}" @selected($parts['frequency'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-field>

        <div x-show="frequency === 'weekly'" x-cloak>
            <x-field :name="'weekday_' . $task->id" label="يوم الأسبوع">
                <select id="weekday_{{ $task->id }}" name="weekday" class="select">
                    @foreach ($weekdays as $value => $label)
                        <option value="{{ $value }}" @selected($parts['weekday'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>

        <div x-show="frequency === 'monthly'" x-cloak>
            <x-field :name="'monthday_' . $task->id" label="يوم الشهر"
                     hint="من 1 لـ 28 — عشان الشهور القصيرة ماتتخطاش.">
                <input type="number" min="1" max="28" id="monthday_{{ $task->id }}" name="monthday"
                       class="input" value="{{ $parts['monthday'] }}">
            </x-field>
        </div>

        <div x-show="['daily', 'weekly', 'monthly'].includes(frequency)" x-cloak>
            <x-field :name="'hour_' . $task->id" label="الساعة">
                <input type="number" min="0" max="23" id="hour_{{ $task->id }}" name="hour"
                       class="input u-mono" value="{{ $parts['hour'] }}">
            </x-field>
        </div>

        <div x-show="frequency !== 'custom'" x-cloak>
            <x-field :name="'minute_' . $task->id" label="الدقيقة">
                <input type="number" min="0" max="59" id="minute_{{ $task->id }}" name="minute"
                       class="input u-mono" value="{{ $parts['minute'] }}">
            </x-field>
        </div>

        <div x-show="frequency === 'custom'" x-cloak>
            <x-field :name="'cron_' . $task->id" label="صيغة cron"
                     hint="خمس خانات: دقيقة، ساعة، يوم الشهر، الشهر، يوم الأسبوع.">
                <input type="text" id="cron_{{ $task->id }}" name="cron" dir="ltr"
                       class="input u-mono u-ltr" value="{{ $parts['cron'] }}" maxlength="100">
            </x-field>
        </div>
    </div>

    <div class="cron-foot">
        <label class="cron-toggle">
            <input type="checkbox" name="is_enabled" value="1" @checked($task->is_enabled)>
            شغّالة
        </label>

        @if ($task->touchesPoints())
            {{-- Worth saying out loud on this one: turning it off stops points
                 moving and nothing on any other screen looks different. --}}
            <span class="cron-foot__warn">لو قفلتها، خصم التأخير هيقف ومحدش هياخد باله غير وقت المكافآت.</span>
        @endif

        <x-button variant="secondary" size="sm">احفظ الميعاد</x-button>
    </div>
</form>
