<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-02) Per-user permission overrides on top of the role baseline.
 *
 * Until now a permission was purely a property of the role: to let ONE person
 * do something, you either gave it to everyone sharing their role or invented a
 * near-duplicate role for them. Both are how permission tables rot.
 *
 * This table is deliberately an override list, not a replacement: a user's
 * effective permissions are their role's set, plus every row here with
 * granted = true, minus every row with granted = false. A user with no rows
 * behaves exactly as they do today, so the table starts empty and changes
 * nothing until an admin uses it.
 *
 * granted is a real boolean rather than two tables (grants/revokes) because the
 * admin screen edits one checkbox per permission and needs to distinguish three
 * states — "as the role says", "forced on", "forced off" — which is exactly
 * "no row", "row true", "row false".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            // true = granted on top of the role, false = taken away from it.
            $table->boolean('granted');
            $table->timestamps();

            // One verdict per permission per user — a user can't both have and
            // not have the same key.
            $table->unique(['user_id', 'permission_id']);
            // hasPermission() loads the whole table at once — see overrideMap().
            $table->index('user_id');
        });

        $this->bustCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');

        $this->bustCache();
    }

    /**
     * User::overrideMap() is a rememberForever cache keyed by user id, and
     * nothing in a seed run writes to permission_user, so no model event ever
     * fires to clear it. Verified: after `migrate:fresh --seed` the cache still
     * held the previous database's exceptions and applied them to the freshly
     * seeded user who happened to reuse the same id — a revoked permission
     * leaking across a database reset. Migrations are the one hook that runs on
     * fresh/refresh/migrate alike, so the clear belongs here.
     */
    private function bustCache(): void
    {
        Cache::forget(User::OVERRIDES_CACHE_KEY);
    }
};
