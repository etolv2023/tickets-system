<?php

namespace App\Support;

use Cron\CronExpression;

/**
 * ★ (2026-08-29) Between "كل يوم الساعة ٣" and `0 3 * * *`.
 *
 * The schedule screen offers dropdowns because that is how the two tasks this
 * system has are actually described, and a mistyped cron field is the kind of
 * mistake that runs a points sweep every minute instead of every hour. But the
 * dropdowns cannot express everything, so a raw expression stays available
 * behind «متقدم» — validated, never trusted as typed.
 *
 * Only ever produces a five-field expression, and the caller must still check
 * the result with CronExpression::isValidExpression() before storing it: the
 * fields here are clamped, but "clamped" is not the same as "checked".
 */
final class CronPreset
{
    public const FREQUENCIES = [
        'hourly' => 'كل ساعة',
        'daily' => 'كل يوم',
        'weekly' => 'كل أسبوع',
        'monthly' => 'كل شهر',
        'custom' => 'متقدم — cron خام',
    ];

    /** Cron numbers the days of the week from Sunday = 0. */
    public const WEEKDAYS = [
        6 => 'السبت',
        0 => 'الأحد',
        1 => 'الاتنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
    ];

    /**
     * Build a cron expression from the form's parts.
     *
     * @param  array<string, mixed>  $input
     */
    public static function toExpression(array $input): string
    {
        $frequency = (string) ($input['frequency'] ?? 'custom');

        if ($frequency === 'custom') {
            return trim((string) ($input['cron'] ?? ''));
        }

        $minute = self::clamp($input['minute'] ?? 0, 0, 59);
        $hour = self::clamp($input['hour'] ?? 0, 0, 23);
        $weekday = self::clamp($input['weekday'] ?? 0, 0, 6);
        $monthday = self::clamp($input['monthday'] ?? 1, 1, 28);

        return match ($frequency) {
            'hourly' => "{$minute} * * * *",
            'daily' => "{$minute} {$hour} * * *",
            'weekly' => "{$minute} {$hour} * * {$weekday}",
            // Capped at 28 on purpose: "the 31st" silently skips February and
            // the short months, which is never what anybody meant by "monthly".
            'monthly' => "{$minute} {$hour} {$monthday} * *",
            default => trim((string) ($input['cron'] ?? '')),
        };
    }

    /**
     * Read an expression back into the form's parts, so opening the editor
     * shows what is actually configured rather than an empty default.
     *
     * Anything that is not exactly one of the shapes above comes back as
     * 'custom' — a lossy guess would show a person one schedule while the
     * server runs another.
     *
     * @return array{frequency: string, minute: int, hour: int, weekday: int, monthday: int, cron: string}
     */
    public static function fromExpression(?string $cron): array
    {
        $cron = trim((string) $cron);
        $parts = preg_split('/\s+/', $cron) ?: [];

        $base = [
            'frequency' => 'custom',
            'minute' => 0,
            'hour' => 3,
            'weekday' => 0,
            'monthday' => 1,
            'cron' => $cron,
        ];

        if (count($parts) !== 5) {
            return $base;
        }

        [$minute, $hour, $monthday, $month, $weekday] = $parts;

        // Only plain integers round-trip. A step or a list field is a real
        // expression the dropdowns cannot hold, and pretending otherwise would
        // rewrite it into something else on the next save.
        $numeric = fn (string $v) => ctype_digit($v);

        if ($month !== '*') {
            return $base;
        }

        return match (true) {
            $numeric($minute) && $hour === '*' && $monthday === '*' && $weekday === '*' => [
                ...$base, 'frequency' => 'hourly', 'minute' => (int) $minute,
            ],
            $numeric($minute) && $numeric($hour) && $monthday === '*' && $weekday === '*' => [
                ...$base, 'frequency' => 'daily', 'minute' => (int) $minute, 'hour' => (int) $hour,
            ],
            $numeric($minute) && $numeric($hour) && $monthday === '*' && $numeric($weekday) => [
                ...$base, 'frequency' => 'weekly', 'minute' => (int) $minute,
                'hour' => (int) $hour, 'weekday' => (int) $weekday,
            ],
            $numeric($minute) && $numeric($hour) && $numeric($monthday) && $weekday === '*' => [
                ...$base, 'frequency' => 'monthly', 'minute' => (int) $minute,
                'hour' => (int) $hour, 'monthday' => (int) $monthday,
            ],
            default => $base,
        };
    }

    /**
     * The schedule in Arabic, for the row that shows it.
     *
     * Falls back to the raw expression for anything the presets cannot name.
     * Showing the expression itself beats inventing a sentence for a stepped or
     * listed field, and the row prints the next run time beside it anyway.
     *
     * (No cron sample is written in this docblock on purpose: a step field
     * contains the two characters that close a PHP block comment, and putting
     * one here is a parse error, not a typo you notice later.)
     */
    public static function describe(?string $cron): string
    {
        $parts = self::fromExpression($cron);
        $at = sprintf('%02d:%02d', $parts['hour'], $parts['minute']);

        return match ($parts['frequency']) {
            'hourly' => $parts['minute'] === 0
                ? 'كل ساعة'
                : 'كل ساعة عند الدقيقة ' . $parts['minute'],
            'daily' => 'كل يوم الساعة ' . $at,
            'weekly' => 'كل ' . (self::WEEKDAYS[$parts['weekday']] ?? '؟') . ' الساعة ' . $at,
            'monthly' => 'كل شهر يوم ' . $parts['monthday'] . ' الساعة ' . $at,
            default => (string) $cron,
        };
    }

    /**
     * Valid AND capable of ever firing.
     *
     * isValidExpression() alone is not enough, and the gap is exactly the
     * failure this screen exists to prevent: `0 3 30 2 *` is five syntactically
     * legal fields describing the 30th of February. The parser accepts it, the
     * scheduler accepts it, and the task then never runs again with nothing
     * anywhere saying why. The library knows — getNextRunDate() throws
     * "Impossible CRON expression" — so the check is to ask it.
     */
    public static function isValid(?string $cron): bool
    {
        if (blank($cron) || ! CronExpression::isValidExpression((string) $cron)) {
            return false;
        }

        try {
            (new CronExpression((string) $cron))->getNextRunDate();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function clamp(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }
}
