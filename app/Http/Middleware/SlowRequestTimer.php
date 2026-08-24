<?php

namespace App\Http\Middleware;

use App\Models\Ticket;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * TEMPORARY diagnostic (2026-08-24). Logs any request slower than 3s to
 * the "slow_requests" channel, so an intermittent >60s hang reported on
 * ticket actions can be traced to an exact method+route+duration, with a
 * DB-time vs non-DB-time split. Runs in production deliberately, unlike
 * QueryCount, because that is where the symptom reproduces.
 *
 * Remove this file, its bootstrap/app.php registration, and the
 * "slow_requests" channel in config/logging.php once the cause is found.
 */
class SlowRequestTimer
{
    private const THRESHOLD_MS = 3000.0;

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $startedAt = now();
        $start = microtime(true);

        $dbCount = 0;
        $dbTime = 0.0;
        $dbMaxTime = 0.0;
        $dbMaxSql = null;

        DB::listen(function ($query) use (&$dbCount, &$dbTime, &$dbMaxTime, &$dbMaxSql) {
            $dbCount++;
            $dbTime += $query->time;

            if ($query->time > $dbMaxTime) {
                $dbMaxTime = $query->time;
                $dbMaxSql = $query->sql;
            }
        });

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->logIfSlow(
                $request, $requestId, $startedAt, $start,
                $dbCount, $dbTime, $dbMaxTime, $dbMaxSql,
                status: null, exception: $e,
            );

            throw $e;
        }

        $this->logIfSlow(
            $request, $requestId, $startedAt, $start,
            $dbCount, $dbTime, $dbMaxTime, $dbMaxSql,
            status: $response->getStatusCode(), exception: null,
        );

        return $response;
    }

    private function logIfSlow(
        Request $request,
        string $requestId,
        Carbon $startedAt,
        float $start,
        int $dbCount,
        float $dbTime,
        float $dbMaxTime,
        ?string $dbMaxSql,
        ?int $status,
        ?Throwable $exception,
    ): void {
        $durationMs = (microtime(true) - $start) * 1000;

        if ($durationMs < self::THRESHOLD_MS) {
            return;
        }

        Log::channel('slow_requests')->warning('slow_request', [
            'request_id' => $requestId,
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'duration_ms' => round($durationMs, 1),
            'method' => $request->method(),
            'uri' => $request->path(),
            'route' => optional($request->route())->getName(),
            'user_id' => optional($request->user())->id,
            'ticket_id' => $this->ticketId($request),
            'status' => $status,
            'exception' => $exception ? get_class($exception) : null,
            'db_count' => $dbCount,
            'db_time_ms' => round($dbTime, 1),
            'db_max_time_ms' => round($dbMaxTime, 1),
            // SQL template only — $query->sql carries "?" placeholders, never
            // the bound values. Never pass bindings here.
            'db_max_sql' => $dbMaxSql === null
                ? null
                : Str::limit(preg_replace('/\s+/', ' ', $dbMaxSql), 1000, '…'),
        ]);
    }

    private function ticketId(Request $request): int|string|null
    {
        $ticket = $request->route('ticket');

        return match (true) {
            $ticket instanceof Ticket => $ticket->getKey(),
            is_scalar($ticket) => $ticket,
            default => null,
        };
    }
}
