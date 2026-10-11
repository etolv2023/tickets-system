<?php

namespace App\Support;

use Closure;

/**
 * Runs a callback under another locale and always restores the current one.
 *
 * For text that is built once and stored (ledger reasons, notification bodies):
 * its language must not depend on whichever UI language the person who
 * triggered it happened to be using.
 */
final class Locale
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function as(string $locale, Closure $callback): mixed
    {
        $original = app()->getLocale();

        if ($original === $locale) {
            return $callback();
        }

        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($original);
        }
    }
}
