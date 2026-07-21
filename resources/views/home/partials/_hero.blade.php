{{-- The header band: greeting fused with the four action counts as a live
     pulse-strip. Each chip tints its hue only when count > 0 (calm slate at 0,
     so a quiet day reads quiet), carries an <x-icon> glyph and a mono figure,
     and anchor-scrolls to its own section below. These chips are the command
     bar — deliberately a different visual species (small pill, --text-lg) from
     the large monthly KPI tiles in the overview zone, so the two number groups
     can never be mistaken for the same row twice. --}}
<div class="today__hero">
    <div class="today__hero-mark">
        <x-icon name="home" />
    </div>

    <div class="today__hero-text">
        <h1 class="today__greeting">أهلاً، {{ $user->name }}</h1>
        <p class="today__date">{{ now(config('app.display_timezone'))->translatedFormat('l j F Y') }}</p>
    </div>

    <nav class="pulse-strip" aria-label="اختصارات الشغل المستحق">
        <a @class(['pulse-chip', 'pulse-chip--red', 'pulse-chip--active' => $counts['onFire'] > 0]) href="#sec-onfire">
            <x-icon name="alert" class="pulse-chip__icon" />
            <span class="pulse-chip__figure">{{ $counts['onFire'] }}</span>
            <span class="pulse-chip__label">بيولّع</span>
        </a>

        @can('features.approve')
            <a @class(['pulse-chip', 'pulse-chip--amber', 'pulse-chip--active' => $counts['awaitingMe'] > 0]) href="#sec-approve">
                <x-icon name="inbox" class="pulse-chip__icon" />
                <span class="pulse-chip__figure">{{ $counts['awaitingMe'] }}</span>
                <span class="pulse-chip__label">قراري</span>
            </a>
        @endcan

        <a @class(['pulse-chip', 'pulse-chip--teal', 'pulse-chip--active' => $counts['blockingOthers'] > 0]) href="#sec-blocking">
            <x-icon name="block" class="pulse-chip__icon" />
            <span class="pulse-chip__figure">{{ $counts['blockingOthers'] }}</span>
            <span class="pulse-chip__label">مأخّر</span>
        </a>

        <a @class(['pulse-chip', 'pulse-chip--green', 'pulse-chip--active' => $counts['onMyPlate'] > 0]) href="#sec-plate">
            <x-icon name="list-checks" class="pulse-chip__icon" />
            <span class="pulse-chip__figure">{{ $counts['onMyPlate'] }}</span>
            <span class="pulse-chip__label">دماغي</span>
        </a>
    </nav>
</div>
