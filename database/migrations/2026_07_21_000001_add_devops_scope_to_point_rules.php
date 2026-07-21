<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F18 (2026-07-21): DevOps becomes a real ticket SCOPE, not only a side.
 *
 * Until now scope named which DEVELOPMENT sides a ticket needs (frontend /
 * backend / both), and a purely-devops ticket had nowhere to sit — it borrowed
 * the (type, 'any', side) fallback. By explicit request DevOps is now its own
 * mutually-exclusive scope on the ticket form, so a deployment/infra ticket can
 * be classified as such and carry its own matrix column that subtask points
 * derive from, exactly like every other scope.
 *
 * The scope enum is widened here; the default point values are SEEDED as a copy
 * of each type's existing 'any' row (the agreed starting point — the admin tunes
 * them from /admin/point-rules afterwards). Doctrine/DBAL isn't installed, so the
 * enum change goes through raw SQL like the other enum migrations.
 *
 * The INSERT…SELECT copies 'any' → 'devops' for an already-populated database
 * (a real deployment running `migrate`). On a fresh `migrate:fresh` the table is
 * still empty at migration time, so it copies nothing and PointRuleSeeder plants
 * the devops rows instead — the two never collide, and the NOT EXISTS guard keeps
 * a re-run idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The ticket's own scope column, so a ticket can actually be classified
        // devops on the create/edit form.
        DB::statement(
            "ALTER TABLE tickets MODIFY scope ENUM('frontend','backend','both','devops','inquiry','undefined') NOT NULL DEFAULT 'undefined'"
        );

        DB::statement(
            "ALTER TABLE point_rules MODIFY scope ENUM('frontend','backend','both','any','devops') NOT NULL"
        );

        DB::statement(
            'INSERT INTO point_rules (ticket_type, scope, side, points, is_active, created_at, updated_at)
             SELECT p.ticket_type, "devops", p.side, p.points, p.is_active, NOW(), NOW()
             FROM point_rules p
             WHERE p.scope = "any"
               AND NOT EXISTS (
                   SELECT 1 FROM point_rules d
                   WHERE d.ticket_type = p.ticket_type AND d.scope = "devops" AND d.side = p.side
               )'
        );
    }

    public function down(): void
    {
        DB::statement('DELETE FROM point_rules WHERE scope = "devops"');

        // Fold any devops-scoped tickets back to 'undefined' before narrowing
        // the enum, or the ALTER would truncate them.
        DB::statement('UPDATE tickets SET scope = "undefined" WHERE scope = "devops"');

        DB::statement(
            "ALTER TABLE point_rules MODIFY scope ENUM('frontend','backend','both','any') NOT NULL"
        );

        DB::statement(
            "ALTER TABLE tickets MODIFY scope ENUM('frontend','backend','both','inquiry','undefined') NOT NULL DEFAULT 'undefined'"
        );
    }
};
