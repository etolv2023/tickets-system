{{-- ★★★★★ (2026-07-21) The monthly scoreboard — the same four totals as before
     (DashboardService::kpis → Ticket::visibleTo), now icon-led via <x-stat-tile>.
     Large mono figures, hue on the 3px edge + head glyph only (never a card
     wash), so they stay a calm scoreboard while the header pulse-chips carry the
     live "act now" heat. The "نظرة عامة" label moved to the zone divider. --}}
<div class="today-stats">
    <x-stat-tile variant="slate" icon="ticket" :figure="$kpis['open']" caption="تذكرة مفتوحة دلوقتي" />
    <x-stat-tile variant="green" icon="check-circle" :figure="$kpis['resolvedThisMonth']" caption="اتحلت الشهر ده" />
    <x-stat-tile variant="teal" icon="timer" :figure="$kpis['avgResolutionHours']" unit="س" caption="متوسط ساعات الحل" />
    <x-stat-tile variant="amber" icon="alert" :figure="$kpis['slaBreachesThisMonth']" caption="خرق SLA الشهر ده" />
</div>
