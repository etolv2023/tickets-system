{{-- Zone 3 — the monthly overview. A real labelled divider band (replacing the
     old orphan "نظرة عامة" line), then the large KPI scoreboard, then the widget
     grid. The KPI tiles are deliberately the opposite visual weight of the
     header pulse-chips — big mono figures, not small pills — so the two number
     groups read as two different things. All widgets sit in one masonry
     (.overview-grid): a gated widget a role can't see simply doesn't emit and
     the rest repack with no hole (CLAUDE.md § 6 — no full-width empty box). --}}
<div class="zone-head">
    <h2 class="zone-head__title">نظرة عامة على الفريق</h2>
    <span class="zone-head__meta">{{ now(config('app.display_timezone'))->translatedFormat('F Y') }}</span>
</div>

@include('home.partials._kpis')

<div class="overview-grid">
    @include('home.partials._ticket-stats')
    @include('home.partials._sla-overview')
    @include('home.partials._recent-tickets')
    @include('home.partials._team-performance')
    @include('home.partials._recent-activity')
</div>
