<?php

namespace App\Providers;

use App\Listeners\RecordScheduledTask;
use App\Models\Setting;
use App\Models\User;
use App\Services\VirusScanner;
use App\Support\ImpersonationContext;
use Carbon\Carbon;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Its constructor takes host/port/enabled, not classes, so the
        // container cannot work it out on its own.
        $this->app->singleton(VirusScanner::class, fn () => VirusScanner::fromConfig());

        /*
         * ★ (2026-08-29) F29 — and it MUST be a singleton.
         *
         * TrackImpersonation fills it and ActivityLogger reads it. Without this
         * line the container hands each of them a fresh instance, the middleware
         * writes into one nobody reads, and every action taken while
         * impersonating is logged with impersonation_id = null — the log looks
         * complete and quietly loses the only fact it was added to record.
         *
         * That is exactly what happened the first time this was tested: the
         * status change landed, the log row was written under the right user,
         * and the session it belonged to was missing.
         */
        $this->app->singleton(ImpersonationContext::class);
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureGates();
        $this->configureViews();
        $this->configureScheduleLog();

        // Laravel's stock pagination view is a wall of Tailwind utilities and
        // ships left/right classes that don't flip. Ours is in resources/views
        // /vendor/pagination and reads from the same tokens as everything else.
        Paginator::defaultView('vendor.pagination.default');
        Paginator::defaultSimpleView('vendor.pagination.default');

        Carbon::setLocale('ar');
    }

    /**
     * ★ (2026-08-29) Records every scheduled run onto its row, so
     * /admin/scheduled-tasks can say whether a task actually ran and whether it
     * worked — a schedule you cannot verify is a promise, not a fact.
     *
     * Registered by hand rather than by event discovery, which this application
     * does not enable: three listeners in one place beats a convention that has
     * to be remembered.
     */
    private function configureScheduleLog(): void
    {
        Event::listen(ScheduledTaskStarting::class, [RecordScheduledTask::class, 'starting']);
        Event::listen(ScheduledTaskFinished::class, [RecordScheduledTask::class, 'finished']);
        Event::listen(ScheduledTaskFailed::class, [RecordScheduledTask::class, 'failed']);
    }

    /**
     * Branding for the chrome. A composer, not View::share: this reads the
     * database, and during /install there isn't one yet. Composers only run
     * when the view actually renders, and the install layout never asks.
     */
    private function configureViews(): void
    {
        View::composer(['layouts.app', 'layouts.auth', 'layouts.portal'], function ($view) {
            $view->with([
                'appName' => Setting::get('app_name', 'نظام التذاكر'),
                'appLogo' => Setting::get('app_logo'),
            ]);
        });

        // The bell's unread count. One cheap COUNT per page for a logged-in
        // user, cached for a minute so a burst of navigation doesn't repeat it.
        // The bell lives in the topbar; the cache means naming both costs
        // nothing extra.
        View::composer('partials.topbar', function ($view) {
            $user = auth()->user();

            $view->with('unreadCount', $user === null ? 0 : Cache::remember(
                "notif.unread.{$user->id}",
                now()->addMinute(),
                fn () => $user->unreadNotifications()->count(),
            ));
        });
    }

    private function configureModels(): void
    {
        $strict = ! $this->app->isProduction();

        // Any N+1 becomes an exception in development, never a slow page in
        // production (CLAUDE.md § 4.1).
        Model::preventLazyLoading($strict);

        // Assigning a column that doesn't exist throws instead of being silently
        // dropped — the cheapest possible guard against an invented column (§ 2.1).
        Model::preventSilentlyDiscardingAttributes($strict);
    }

    /**
     * Every permission key doubles as a gate, so @can('settings.manage') works
     * without defining 29 gates by hand. Returning null (not false) on a miss
     * lets model policies still have their say.
     */
    private function configureGates(): void
    {
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });
    }
}
