<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * The convention: a branch name starts with the ticket number — F27.
 *
 * GENERATION IS STRICT, MATCHING IS TOLERANT, and that is not a contradiction.
 * Every name handed out by this system begins with the ticket number and
 * nothing else, so `git branch` in any repository sorts itself by ticket. But
 * branches created by hand before this existed carry a Git-Flow prefix, and
 * refusing to recognise `feature/TK-2026-00042` would report finished work as
 * missing — which is the one failure this whole feature exists to prevent.
 *
 * One segment of prefix, never two: `team/feature/TK-…` is not recognised,
 * because past one level the ticket number stops being findable at a glance.
 *
 * Knows nothing about a Ticket model on purpose — it takes and returns strings,
 * so the matcher can run over a thousand branch names without touching the
 * database (CLAUDE.md § 3).
 */
class BranchNamingService
{
    /**
     * The shape TicketNumberService actually produces: TK-YYYY-NNNNN.
     *
     * Written to match that service rather than "any word before a dash": a
     * looser pattern would happily read `WIP-2026-1` as a ticket number and
     * attach a branch to a ticket that does not exist.
     */
    private const TICKET = 'TK-\d{4}-\d+';

    /** Keeps a generated name inside what a terminal shows without wrapping. */
    private const MAX_LENGTH = 60;

    /**
     * The name this system tells a developer to use.
     *
     * An Arabic title is NOT appended, and this is the interesting decision
     * here. Str::slug does not drop Arabic — it transliterates it, so
     * «فاتورة مكررة في التقرير» comes out as `fator-mkrr-fy-altkryr`. That is
     * unreadable in either language: an Arabic speaker cannot read it and an
     * English speaker learns nothing from it, and it would then sit in the
     * repository forever. The ticket number on its own is a complete, valid,
     * conventional branch name, and it is the part anyone actually looks up.
     *
     * A title already written in English keeps its slug, because there the
     * words survive intact and do help.
     */
    public function suggest(string $ticketNumber, ?string $title = null): string
    {
        $ticketNumber = strtoupper(trim($ticketNumber));

        if ($title === null || preg_match('/\p{Arabic}/u', $title) === 1) {
            return $ticketNumber;
        }

        $slug = Str::slug(Str::words($title, 6, ''));

        if ($slug === '') {
            return $ticketNumber;
        }

        return Str::limit($ticketNumber . '-' . $slug, self::MAX_LENGTH, '');
    }

    /**
     * The ticket number a branch name claims, or null if it claims none.
     *
     * @return string|null uppercased, so it compares against tickets.ticket_number
     */
    public function ticketNumberIn(string $branch): ?string
    {
        return preg_match($this->pattern(), trim($branch), $m) === 1
            ? strtoupper($m[1])
            : null;
    }

    /** Does this branch name belong to this specific ticket? */
    public function matches(string $branch, string $ticketNumber): bool
    {
        return $this->ticketNumberIn($branch) === strtoupper(trim($ticketNumber));
    }

    /**
     * Why a name was rejected, in Arabic, for a form error.
     *
     * Returns null when the name is acceptable. Separate from matches() because
     * a validator needs to say WHICH rule was broken — "this is some other
     * ticket's branch" and "this has no ticket number at all" are different
     * mistakes with different fixes.
     */
    public function rejectionReason(string $branch, string $ticketNumber): ?string
    {
        $branch = trim($branch);

        if ($branch === '') {
            return 'اكتب اسم البرانش.';
        }

        // Git's own rules, the subset that a typed name realistically breaks.
        if (preg_match('/(^[\/.]|[\/.]$|\.\.|[\s~^:?*\[\\\\]|\/\/)/', $branch) === 1) {
            return 'اسم البرانش فيه حروف مش مسموح بيها في جيت.';
        }

        $found = $this->ticketNumberIn($branch);

        if ($found === null) {
            return 'اسم البرانش لازم يبدأ برقم التذكرة — يعني «' . $this->suggest($ticketNumber) . '»'
                . (config('github.allow_type_prefix') ? '، أو «feature/' . $this->suggest($ticketNumber) . '».' : '.');
        }

        if ($found !== strtoupper(trim($ticketNumber))) {
            return 'البرانش ده بتاع تذكرة تانية (' . $found . ').';
        }

        return null;
    }

    /**
     * The one regex both readers use.
     *
     * Built from config each call rather than cached in a property: the cost is
     * a string concat, and a service that memoises configuration is a service
     * that keeps serving the old value after the setting changes.
     */
    private function pattern(): string
    {
        $prefix = '';

        if (config('github.allow_type_prefix')) {
            $names = array_map(
                fn (string $p) => preg_quote($p, '/'),
                (array) config('github.type_prefixes', [])
            );

            if ($names !== []) {
                $prefix = '(?:(?:' . implode('|', $names) . ')\/)?';
            }
        }

        // The tail must start with a separator or not exist at all, so
        // TK-2026-00042x is not read as TK-2026-00042.
        return '/^' . $prefix . '(' . self::TICKET . ')(?:[-_\/].*)?$/i';
    }
}
