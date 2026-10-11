<?php

namespace App\Support;

use Closure;

/**
 * Values held for the length of one request (or one queue job — the
 * container forgets scoped instances between jobs).
 *
 * For the maps that are cached forever and read constantly: settings,
 * holidays, the permission maps. With the file cache store every read of one
 * of those is a file open plus an unserialize, and a single @can ran about six
 * of them — a month calendar of two thousand items spent most of its render
 * re-reading the same unchanged files. The maps are still cached forever and
 * busted on write; whoever busts a cache key forgets it here too, so a change
 * made earlier in a request is seen later in it.
 */
final class RequestMemo
{
    /** @var array<string, mixed> */
    private array $values = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public function remember(string $key, Closure $resolve): mixed
    {
        if (! array_key_exists($key, $this->values)) {
            $this->values[$key] = $resolve();
        }

        return $this->values[$key];
    }

    public function forget(string $key): void
    {
        unset($this->values[$key]);
    }
}
