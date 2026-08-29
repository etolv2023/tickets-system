<?php

namespace App\Support;

use App\Enums\SubtaskSide;
use App\Models\GithubRepository;
use App\Models\Role;
use App\Models\Ticket;

/**
 * ★ (2026-08-29) F27 — "ليها برانش" is not one question, it is one per side.
 *
 * A ticket with backend AND frontend work owes a backend branch AND a frontend
 * branch. One of the two is not half-done, it is WRONG — and a screen that
 * answers with a single yes/no hides exactly that case, which is the one worth
 * catching. So the row shows a chip per side instead of a count.
 *
 * WHAT COUNTS AS OWED is read from the ticket's role assignments, not from its
 * subtasks: the assignment is the ticket's commitment ("مبرمج باك عليه شغل
 * هنا"), while a subtask is a plan that may not exist yet. A ticket nobody is
 * assigned to owes nothing.
 *
 * Sides with no repository behind them owe nothing either. Nobody pushes a
 * branch for «تيستر» — there is no tester repository — so a tester on the
 * ticket must never make it look incomplete.
 *
 * A side is covered by a branch in ANY repository of that side. There are three
 * frontend repositories; frontend work lives in one of them, not all three.
 *
 * PURE DERIVATION, ZERO QUERIES. Everything it reads is either eager-loaded on
 * the ticket (roleAssignments, branches) or a cached map (Role::byId,
 * GithubRepository::lookup). Called once per row on a 25-row list.
 */
final class BranchCoverage
{
    /**
     * One entry per side this ticket touches, owed or already covered.
     *
     * @return array<int, array{side: string, label: string, owed: bool, covered: bool}>
     */
    public static function for(Ticket $ticket): array
    {
        $repos = GithubRepository::lookup();
        $roles = Role::byId();

        // Sides a repository actually exists for. Anything else cannot be owed.
        $repoSides = [];

        foreach ($repos as $repo) {
            if ($repo->is_active && $repo->side !== null) {
                $repoSides[$repo->side->value] = true;
            }
        }

        // Owed: every assigned role whose key names one of those sides.
        $owed = [];

        foreach ($ticket->roleAssignments as $assignment) {
            $key = $roles[$assignment->role_id]->key ?? null;

            if ($key !== null && isset($repoSides[$key])) {
                $owed[$key] = true;
            }
        }

        // Covered: every side that has at least one branch, whether it was owed
        // or not. A branch nobody expected is worth showing — it usually means
        // the assignment is wrong, not the branch.
        $covered = [];

        foreach ($ticket->branches as $branch) {
            $side = $repos[$branch->github_repository_id]->side?->value ?? null;

            if ($side !== null) {
                $covered[$side] = true;
            }
        }

        $sides = array_keys($owed + $covered);

        return array_map(fn (string $side) => [
            'side' => $side,
            'label' => SubtaskSide::tryFrom($side)?->label() ?? $side,
            'owed' => isset($owed[$side]),
            'covered' => isset($covered[$side]),
        ], $sides);
    }

    /**
     * A side somebody is assigned to, with no branch anywhere for it.
     *
     * This is the finding the screen exists for — narrower and more useful than
     * "no branch at all", because it catches the half-done ticket that a plain
     * count reports as fine.
     */
    public static function isIncomplete(Ticket $ticket): bool
    {
        foreach (self::for($ticket) as $entry) {
            if ($entry['owed'] && ! $entry['covered']) {
                return true;
            }
        }

        return false;
    }
}
