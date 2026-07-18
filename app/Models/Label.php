<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
{
    /**
     * The only colours a label may take. Names of semantic tokens, not hex —
     * a free picker would break "every coloured pixel means something" (§ 6).
     *
     * @var array<string, string>
     */
    /* The full picker. Keys are hue names, not role names: an admin choosing a
       colour for a custom status is picking "أزرق", not "inquiry".
       Every hue here has a matching --c-<key> / --c-<key>-bg token pair in
       tokens.css and a .badge--<key> / .stripe--<key> rule. Adding one means
       adding all three. */
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
        'blue' => 'أزرق',
        'indigo' => 'نيلي',
        'violet' => 'بنفسجي',
        'plum' => 'خوخي',
        'brown' => 'بني',
        'slate' => 'رمادي',
    ];

    protected $fillable = ['name', 'color', 'created_by'];

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
