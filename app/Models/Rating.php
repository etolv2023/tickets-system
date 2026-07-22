<?php

namespace App\Models;

use App\Enums\PointSide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = 'rated_at';

    protected $fillable = ['ticket_id', 'ratee_id', 'role_id', 'side', 'score', 'comment', 'rated_by'];

    protected function casts(): array
    {
        return ['side' => PointSide::class, 'score' => 'integer', 'rated_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** The role the person was rated as (role-based since 2026-07-24). */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }
}
