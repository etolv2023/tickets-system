<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class Label extends Model
{
    private const PICKER_CACHE_KEY = 'labels.picker';

    /**
     * The only colours a label may take. Names of semantic tokens, not hex —
     * a free picker would break "every coloured pixel means something" (§ 6).
     *
     * @var array<string, string>
     */
    /* The full picker. Keys are hue names, not role names: an admin choosing a
       colour for a custom status is picking "تركوازي", not "progress".
       Every hue here has a matching --c-<key> / --c-<key>-bg token pair in
       tokens.css and a .badge--<key> / .stripe--<key> rule. Adding one means
       adding all three.

       'blue' is deliberately absent (redesign 2026-07-19): blue is reserved
       for interactive chrome — anything blue is a control, never a state.
       Existing blue rows were remapped to teal by migration; the --c-blue
       tokens and .badge--blue/.stripe--blue rules survive only as a safety
       net for rows that predate the remap. */
    public const COLORS = [
        'red' => 'أحمر',
        'rose' => 'وردي',
        'orange' => 'برتقالي',
        'amber' => 'كهرماني',
        'yellow' => 'أصفر',
        'lime' => 'ليموني',
        'green' => 'أخضر',
        'teal' => 'تركوازي',
        'cyan' => 'سماوي',
        'indigo' => 'نيلي',
        'violet' => 'بنفسجي',
        'plum' => 'خوخي',
        'brown' => 'بني',
        'slate' => 'رمادي',
    ];

    protected $fillable = ['name', 'color', 'created_by'];

    protected static function booted(): void
    {
        // ★ (2026-08-04) Same treatment as Role/Priority/LinkType (§ 4.7): the
        // picker is admin-managed reference data that changes a few times a
        // year and was being re-read on every ticket page.
        $bust = fn () => Cache::forget(self::PICKER_CACHE_KEY);

        static::saved($bust);
        static::deleted($bust);
    }

    /**
     * The label picker on the ticket page — id, name and colour, nothing else.
     *
     * @return Collection<int, Label>
     */
    public static function pickerList(): Collection
    {
        return Cache::rememberForever(
            self::PICKER_CACHE_KEY,
            fn () => static::query()->orderBy('name')->get(['id', 'name', 'color'])
        );
    }

    public function tickets(): BelongsToMany
    {
        // See Ticket::labels() — the table is ticket_label, not Laravel's
        // alphabetical default.
        return $this->belongsToMany(Ticket::class, 'ticket_label');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
