<?php

namespace App\Support;

/**
 * How a ticket looks on Discord — every badge, every colour, every bit of
 * bidirectional-text handling, in one place.
 *
 * It exists so that "what emoji is «تم الحل»?" has exactly one answer. Scatter
 * that across the notifier, the job and the embed builders and the card starts
 * disagreeing with the timeline about the same ticket.
 *
 * Nothing here touches the database or the domain. It is handed labels, keys and
 * variants that the caller already resolved, and returns strings.
 */
final class DiscordPresenter
{
    /*
     * Bidi controls. Discord renders raw Unicode, so mixed Arabic/English lines
     * are laid out by the reader's own bidi algorithm — which, left alone, moves
     * things. In an Arabic (right-to-left) line an English title drifts, a
     * ticket number can end up with its parts reordered, and an arrow points the
     * wrong way. These characters scope that algorithm without changing a single
     * stored byte.
     */

    /** First Strong Isolate — lay this value out by its OWN direction, whatever the line's is. */
    private const FSI = "\u{2068}";

    /** Left-to-Right Isolate — force this run to read left-to-right. */
    private const LRI = "\u{2066}";

    /** Pop Directional Isolate — closes either of the above. */
    private const PDI = "\u{2069}";

    /**
     * The seeded system statuses. Admins can add their own, which is why
     * anything not listed falls through to the variant rule below rather than
     * being guessed at.
     */
    private const STATUS_ICONS = [
        'new' => '🆕',
        'pending_approval' => '⏳',
        'rejected' => '🚫',
        'assigned' => '📋',
        'in_progress' => '🔵',
        'dev_done' => '🛠️',
        'testing' => '🧪',
        'reopened' => '♻️',
        'resolved' => '✅',
        'closed' => '⚫',
    ];

    private const PRIORITY_ICONS = [
        'urgent' => '🔴',
        'high' => '🟠',
        'medium' => '🟡',
        'low' => '🟢',
    ];

    private const SUBTASK_STATUS_ICONS = [
        'todo' => '⚪',
        'in_progress' => '🔵',
        'done' => '✅',
        'blocked' => '⛔',
    ];

    /** Roles are seeded, but an admin may add one — hence the neutral default. */
    private const ROLE_ICONS = [
        'backend' => '🧑‍💻',
        'frontend' => '🎨',
        'tester' => '🧪',
        'devops' => '⚙️',
        'support' => '🎧',
        'manager' => '📈',
        'admin' => '🛡️',
    ];

    /** Badge colours, keyed by the variant a status or priority already carries. */
    private const PALETTE = [
        'red' => 'b4433a', 'rose' => 'b04a63', 'orange' => 'a95f2a', 'amber' => 'a9720f',
        'yellow' => '967c12', 'lime' => '5f7d2a', 'green' => '3d7a52', 'teal' => '2f7468',
        'cyan' => '2d6f7d', 'blue' => '3a6ea5', 'indigo' => '5257a8', 'violet' => '7a51a5',
        'plum' => '90467f', 'brown' => '7d5a3c', 'slate' => '5b6570',
    ];

    /**
     * Lays a value out by its own direction.
     *
     * Use it on anything a human typed or is named after: titles, people, company
     * names, role names. An English title on an Arabic line stops drifting; an
     * Arabic name beside an English label stops swapping ends.
     */
    public static function isolate(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '—' : self::FSI . $value . self::PDI;
    }

    /**
     * Forces a left-to-right run.
     *
     * For identifiers that are read as one unit — `TK-2026-00551` — and for
     * transitions, where "from" must stay on the left however the surrounding
     * Arabic wants to arrange it.
     */
    public static function ltr(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '—' : self::LRI . $value . self::PDI;
    }

    /**
     * «A → B», guaranteed to read in that order.
     *
     * Each side is isolated by its own direction, and the pair is wrapped
     * left-to-right so the arrow keeps pointing from the old value to the new
     * one even on an Arabic line.
     */
    public static function transition(?string $from, ?string $to, string $emptyLabel = 'غير موزع'): string
    {
        return self::ltr(
            self::isolate($from ?: $emptyLabel) . ' → ' . self::isolate($to ?: $emptyLabel)
        );
    }

    /** «🔵 جاري العمل» — icon by key, else by the colour the admin chose. */
    public static function status(?string $key, ?string $label, ?string $variant, ?bool $isOpen = null): string
    {
        $icon = self::STATUS_ICONS[$key] ?? self::statusFallbackIcon($variant, $isOpen);

        return $icon . ' ' . self::isolate($label);
    }

    public static function priority(?string $key, ?string $label, ?string $variant): string
    {
        $icon = self::PRIORITY_ICONS[$key] ?? self::variantIcon($variant);

        return $icon . ' ' . self::isolate($label);
    }

    public static function subtaskStatus(?string $key, ?string $label, ?string $variant): string
    {
        $icon = self::SUBTASK_STATUS_ICONS[$key] ?? self::variantIcon($variant);

        return $icon . ' ' . self::isolate($label);
    }

    public static function role(?string $key, ?string $label): string
    {
        return (self::ROLE_ICONS[$key] ?? '👥') . ' ' . self::isolate($label);
    }

    /** Embed colour, as the integer Discord wants. */
    public static function color(?string $variant): int
    {
        return (int) hexdec(self::PALETTE[$variant] ?? self::PALETTE['slate']);
    }

    /** A ticket number, kept intact and left-to-right, in inline code. */
    public static function ticketNumber(?string $number): string
    {
        return self::ltr('`' . ($number ?: '—') . '`');
    }

    /**
     * A status nobody hard-coded: colour first, then whether the workflow still
     * considers the ticket live.
     */
    private static function statusFallbackIcon(?string $variant, ?bool $isOpen): string
    {
        if ($isOpen === false) {
            return '✅';
        }

        return self::variantIcon($variant);
    }

    private static function variantIcon(?string $variant): string
    {
        return match ($variant) {
            'red', 'rose' => '🔴',
            'amber', 'orange', 'brown' => '🟠',
            'yellow', 'lime' => '🟡',
            'green' => '🟢',
            'teal', 'cyan', 'blue' => '🔵',
            'indigo', 'violet', 'plum' => '🟣',
            default => '⚪',
        };
    }

    /** Discord's own timestamp, so everybody sees it in their own timezone. */
    public static function timestamp(?string $iso, string $style = 'f'): string
    {
        if (blank($iso)) {
            return '—';
        }

        try {
            return '<t:' . \Carbon\CarbonImmutable::parse($iso)->getTimestamp() . ":{$style}>";
        } catch (\Throwable) {
            return '—';
        }
    }

    public static function truncate(?string $value, int $limit): string
    {
        $value = (string) $value;

        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit - 1) . '…';
    }
}
