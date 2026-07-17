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
    public const COLORS = [
        'urgent' => 'أحمر',
        'high' => 'برتقالي',
        'medium' => 'كهرماني',
        'low' => 'رمادي',
        'resolved' => 'أخضر',
        'blocked' => 'بني',
        'bug' => 'وردي',
        'inquiry' => 'أزرق',
        'feature' => 'بنفسجي',
        'module' => 'تركوازي',
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
