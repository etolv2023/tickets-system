<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the query count for the request in an X-Query-Count header.
 *
 * CLAUDE.md § 2.1 and § 4 require every screen's query count to be reported,
 * and § 1 rules out Debugbar. This is the DB::listen half of that same line —
 * no package, and it costs nothing in production, where it does not run.
 *
 * Append ?queries=1 to any url in development to have the statements themselves
 * written to the log, which is how a count gets reduced by evidence rather than
 * by guesswork.
 */
class QueryCount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            return $next($request);
        }

        $count = 0;
        $timeMs = 0.0;
        $statements = [];
        $explain = $request->boolean('queries');

        DB::listen(function ($query) use (&$count, &$timeMs, &$statements, $explain) {
            $count++;
            $timeMs += $query->time;

            if ($explain) {
                $statements[] = preg_replace('/\s+/', ' ', $query->sql);
            }
        });

        $response = $next($request);

        $response->headers->set('X-Query-Count', (string) $count);
        $response->headers->set('X-Query-Time', round($timeMs, 1) . 'ms');

        if ($explain) {
            Log::channel('single')->debug("QUERIES for {$request->path()} ({$count})", $statements);
        }

        return $response;
    }
}
