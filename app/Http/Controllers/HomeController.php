<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "اليوم" — F22.1.
 *
 * ★★★★ تعديل (2026-07-20): كانت الصفحة دي ممنوع فيها أي كروت إحصائيات أو
 * أرقام إجمالية بالكامل — "الأرقام مكانها /reports، بتروحلها بقصد". اتلغت
 * القاعدة دي بطلب صريح من العميل (يعايز شكل SaaS تقليدي فيه KPI cards).
 * التفاصيل والسبب في CLAUDE.md § 6 و FEATURES.md F22.1.
 *
 * الأربع أقسام الأصلية (اللي بتولّع / على دماغي / مستني قراري / أنا مأخّر
 * مين) لسه هنا زي ما هي — لسه شغل حقيقي مفيد. اللي اتضاف فوقها وتحتها
 * (KPIs، إحصائيات، SLA، حِمل الفريق، آخر التذاكر، آخر النشاط) كله قراءة بس
 * (read-only) فوق DashboardService و ReportService الموجودين أصلاً — مفيش
 * قاعدة عمل جديدة، ومفيش أي تغيير في منطق الـ workflow أو النقاط أو الـ SLA.
 *
 * كل الاستعلامات (القديمة والجديدة) دلوقتي في DashboardService — الكونترولر
 * ده بقى مجرد تجميع وتمرير للـ View (CLAUDE.md § 3: كونترولر عدّى 150 سطر →
 * المنطق مكانه Service).
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly ReportService $reports,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $onMyPlate = $this->dashboard->onMyPlate($user->id);
        $onFire = $this->dashboard->onFire($user);
        $awaitingMe = $this->dashboard->awaitingMe($user);
        $blockingOthers = $this->dashboard->blockingOthers($user->id);

        $period = CarbonImmutable::now()->format('Y-m');
        [$from, $to] = $this->reports->periodBounds($period);

        return view('home.today', [
            'user' => $user,
            'onMyPlate' => $onMyPlate,
            'onFire' => $onFire,
            'awaitingMe' => $awaitingMe,
            'blockingOthers' => $blockingOthers,

            // The stat tiles must state the real total, not the length of a
            // capped list — a tile that reads 15 while 40 are on fire is worse
            // than no tile. The count only costs a query when the list actually
            // hit the cap, which on a healthy queue is never.
            'counts' => [
                'onMyPlate' => $this->dashboard->totalFor($onMyPlate, fn () => $this->dashboard->onMyPlateQuery($user->id)),
                'onFire' => $this->dashboard->totalFor($onFire, fn () => $this->dashboard->onFireQuery($user)),
                'awaitingMe' => $this->dashboard->totalFor($awaitingMe, fn () => $this->dashboard->awaitingMeQuery($user)),
                'blockingOthers' => $this->dashboard->totalFor($blockingOthers, fn () => $this->dashboard->blockingOthersQuery($user->id)),
            ],

            // ★★★★ New overview widgets — all scoped to this user's visible
            // tickets (Ticket::visibleTo), same as the sections above.
            'kpis' => $this->dashboard->kpis($user, $from, $to),
            'ticketStats' => $this->dashboard->ticketStatsByType($user, $from, $to),
            'slaByPriority' => $this->dashboard->slaBreachesByPriority($user, $from, $to),
            'recentTickets' => $this->dashboard->recentTickets($user),
            'teamPerformance' => $this->dashboard->teamPerformance($user),
            'recentActivity' => $this->dashboard->recentActivity($user),
        ]);
    }
}
