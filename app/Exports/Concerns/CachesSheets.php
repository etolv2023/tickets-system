<?php

namespace App\Exports\Concerns;

/**
 * maatwebsite asks a multi-sheet export for its sheets **twice** per file —
 * once in WriterFactory to create the worksheets, and again in Writer to fill
 * them. An export that builds its rows inside sheets() therefore runs every
 * query in the workbook twice.
 *
 * So sheets() memoises, and the work moves into buildSheets(), which runs once.
 */
trait CachesSheets
{
    /** @var array<int, object>|null */
    private ?array $cachedSheets = null;

    /** @return array<int, object> */
    public function sheets(): array
    {
        return $this->cachedSheets ??= $this->buildSheets();
    }

    /** @return array<int, object> */
    abstract protected function buildSheets(): array;
}
