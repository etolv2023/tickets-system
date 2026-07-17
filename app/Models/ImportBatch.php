<?php

namespace App\Models;

use App\Enums\ImportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'type', 'original_filename', 'stored_name',
        'total_rows', 'created_rows', 'updated_rows', 'failed_rows',
        'status', 'errors', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ImportType::class,
            'errors' => 'array',
            'created_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'في الانتظار',
            'validating' => 'جاري التحقق',
            'previewing' => 'معاينة',
            'importing' => 'جاري الاستيراد',
            'completed' => 'اكتمل',
            'failed' => 'فشل',
            default => $this->status,
        };
    }

    public function statusVariant(): string
    {
        return match ($this->status) {
            'completed' => 'resolved',
            'failed' => 'urgent',
            'importing' => 'inquiry',
            default => 'neutral',
        };
    }
}
