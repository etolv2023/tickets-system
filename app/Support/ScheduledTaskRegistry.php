<?php

namespace App\Support;

/**
 * ★ (2026-08-29) The tasks the schedule screen is allowed to know about.
 *
 * ══════════════════════════════════════════════════════════════════════════
 *  THIS LIST IS THE SECURITY BOUNDARY. THE DATABASE NEVER HOLDS A COMMAND.
 * ══════════════════════════════════════════════════════════════════════════
 *
 * A screen that lets somebody type an artisan command and schedule it is
 * remote code execution behind a permission — one SQL injection, one stolen
 * admin session, one careless grant, and the answer is a shell.
 *
 * So the row in scheduled_tasks carries a KEY, a cron expression and an on/off
 * switch, and nothing else. The key must be one of the constants below or the
 * task is ignored everywhere: the scheduler will not register it, the screen
 * will not list it, and "run now" refuses it. Adding a task is a code change,
 * reviewed like any other. That is the point, not an inconvenience.
 *
 * The Arabic name and description are here rather than in the database for the
 * same reason: they describe what the command does, and only the code knows
 * that.
 */
final class ScheduledTaskRegistry
{
    /**
     * @var array<string, array{name: string, description: string, cron: string, touches_points: bool}>
     */
    public const TASKS = [
        'points:charge-late' => [
            'name' => 'خصم التأخير',
            'description' => 'يفحص المهام الفرعية التي تجاوزت تاريخ استحقاقها ويخصم نقاطها. يعمل كل ساعة لأن مهمة الاستثناء تستحق بعد 4 ساعات عمل، فقد تتأخر الساعة 2 ظهرًا ولن يكتشفها فحص صباحي واحد.',
            'cron' => '0 * * * *',
            // Flagged so the screen can warn before it is switched off: this
            // one decides what people are paid.
            'touches_points' => true,
        ],
        'github:sync' => [
            'name' => 'مزامنة GitHub',
            'description' => 'يقرأ الفروع وطلبات الدمج من المستودعات ويربطها بالتذاكر. يعمل مبكرًا عن قصد — فكل ما يكتبه دليل يُرجع إليه لاحقًا، لذا يجب أن يكون جاهزًا قبل أن يفتح أحد شاشة أو تعمل مهمة مجدولة أخرى.',
            'cron' => '0 3 * * *',
            'touches_points' => false,
        ],
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::TASKS);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::TASKS);
    }

    /** @return array{name: string, description: string, cron: string, touches_points: bool}|null */
    public static function get(string $key): ?array
    {
        return self::TASKS[$key] ?? null;
    }
}
