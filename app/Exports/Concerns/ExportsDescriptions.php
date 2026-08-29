<?php

namespace App\Exports\Concerns;

use Illuminate\Support\Str;

/**
 * The work item's own words, as a spreadsheet cell.
 *
 * Three things have to happen to a description before it can sit in a column,
 * and each of them has a reason:
 *
 *   Bounded in SQL.   `tickets.description` is LONGTEXT and CLAUDE.md § 4.3
 *                     forbids selecting it for a list. An export IS a list —
 *                     five thousand unbounded rows is the exact query that rule
 *                     exists to prevent — so the slice happens in the database
 *                     and only the slice ever crosses the wire.
 *
 *   Stripped to prose. The stored HTML is already purified, but a spreadsheet
 *                     cell is not a browser: <p> and <img> would arrive as
 *                     literal text, and a slice can cut a tag in half.
 *
 *   Bounded again.    Excel refuses a cell over 32,767 characters and takes the
 *                     whole file down with it rather than truncating, so the
 *                     prose gets its own ceiling well under that.
 *
 * PLAIN_LIMIT is the one number to change if a report ever needs more; the SQL
 * slice above it is deliberately larger, because markup inflates the raw column
 * well past the prose it contains.
 */
trait ExportsDescriptions
{
    /** Characters of raw (marked-up) column to read. */
    private const HTML_SLICE = 8000;

    /** Characters of plain prose kept after the markup is stripped. */
    private const PLAIN_LIMIT = 4000;

    /**
     * The bounded slice, as a raw select. Aliased to `description_excerpt`, the
     * same name Ticket::descriptionExcerpt() already reads.
     *
     * The length is a constant expression, never user input (CLAUDE.md § 5).
     */
    protected function descriptionSelect(string $column = 'description'): string
    {
        return 'LEFT(' . $column . ', ' . self::HTML_SLICE . ') AS description_excerpt';
    }

    /**
     * Purified HTML in, one line of readable prose out.
     *
     * Every tag becomes a SPACE rather than being deleted, which strip_tags on
     * its own would do: `<p>عنوان</p><p>تفاصيل</p>` collapses to «عنوانتفاصيل»
     * with the words fused, and a report built on that column would be reading
     * invented words. The whitespace collapse afterwards puts the single space
     * back where two tags met.
     *
     * Entities are decoded last, after the markup is already gone — decoding
     * first would turn an escaped `&lt;b&gt;` back into something the stripper
     * would then eat as a real tag.
     */
    protected function plainDescription(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $text = preg_replace('/<[^>]*>/u', ' ', $html);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : Str::limit($text, self::PLAIN_LIMIT);
    }
}
