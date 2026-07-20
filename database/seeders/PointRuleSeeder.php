<?php

namespace Database\Seeders;

use App\Models\PointRule;
use Illuminate\Database\Seeder;

/**
 * The default matrix, exactly as F18 / PLAN.md § 5 state it.
 *
 *   النوع        النطاق     دعم  فرونت  باك  تيستر
 *   استفسار      any        1    —      —    —
 *   بج           frontend   1    1      —    0.5
 *   بج           backend    1    —      1    0.5
 *   بج           both       1    1      1    0.5
 *   فيتشر        frontend   1    2      —    0.5
 *   فيتشر        backend    1    —      2    0.5
 *   فيتشر        both       1    2      2    0.5
 *   موديول جديد  any        1    3      3    1
 *
 * A dash means the side earns nothing on that combination, so no row exists —
 * the engine logs a warning and awards zero, which is the intended outcome.
 */
class PointRuleSeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string, 2: array<string, float>}> */
    private const MATRIX = [
        ['inquiry', 'any', ['support' => 1]],

        ['bug', 'frontend', ['support' => 1, 'frontend' => 1, 'tester' => 0.5]],
        ['bug', 'backend', ['support' => 1, 'backend' => 1, 'tester' => 0.5]],
        ['bug', 'both', ['support' => 1, 'frontend' => 1, 'backend' => 1, 'tester' => 0.5]],

        ['feature', 'frontend', ['support' => 1, 'frontend' => 2, 'tester' => 0.5]],
        ['feature', 'backend', ['support' => 1, 'backend' => 2, 'tester' => 0.5]],
        ['feature', 'both', ['support' => 1, 'frontend' => 2, 'backend' => 2, 'tester' => 0.5]],

        ['new_module', 'any', ['support' => 1, 'frontend' => 3, 'backend' => 3, 'tester' => 1, 'devops' => 2]],

        // DevOps sits on 'any' rather than getting a row per scope: scope names
        // which DEVELOPMENT sides a ticket needs ('both' literally means
        // frontend+backend), so it says nothing about deployment work. The
        // lookup falls back from (type, scope, side) to (type, 'any', side),
        // which is exactly the shape this needs.
        //
        // ★ These three numbers are a starting point, not a decision. Tune them
        // from /admin/point-rules — firstOrCreate means re-seeding will never
        // overwrite what you set there.
        ['bug', 'any', ['devops' => 1]],
        ['feature', 'any', ['devops' => 1.5]],
    ];

    public function run(): void
    {
        foreach (self::MATRIX as [$type, $scope, $sides]) {
            foreach ($sides as $side => $points) {
                // firstOrCreate, not updateOrCreate: an admin who tuned the
                // matrix must not have it reset by a re-run of the installer.
                PointRule::firstOrCreate(
                    ['ticket_type' => $type, 'scope' => $scope, 'side' => $side],
                    ['points' => $points, 'is_active' => true]
                );
            }
        }
    }
}
