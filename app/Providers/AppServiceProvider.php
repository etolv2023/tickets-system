<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureGates();
        $this->configureViews();

        // Laravel's stock pagination view is a wall of Tailwind utilities and
        // ships left/right classes that don't flip. Ours is in resources/views
        // /vendor/pagination and reads from the same tokens as everything else.
        Paginator::defaultView('vendor.pagination.default');
        Paginator::defaultSimpleView('vendor.pagination.default');

        Carbon::setLocale('ar');
    }

    /**
     * Branding for the chrome. A composer, not View::share: this reads the
     * database, and during /install there isn't one yet. Composers only run
     * when the view actually renders, and the install layout never asks.
     */
    private function configureViews(): void
    {
        View::composer(['layouts.app', 'layouts.auth'], function ($view) {
            $view->with([
                'appName' => Setting::get('app_name', 'نظام التذاكر'),
                'appLogo' => Setting::get('app_logo'),
            ]);
        });

        // The bell's unread count. One cheap COUNT per page for a logged-in
        // user, cached for a minute so a burst of navigation doesn't repeat it.
        View::composer('partials.nav', function ($view) {
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
