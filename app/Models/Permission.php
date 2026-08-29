<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'group', 'name_ar'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(Role::CACHE_KEY));
        static::deleted(fn () => Cache::forget(Role::CACHE_KEY));
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** Arabic label for each group, used as a section header in the matrix. */
    public function groupLabel(): string
    {
        return match ($this->group) {
            'tickets' => 'التذاكر',
            'comments' => 'التعليقات',
            'workflow' => 'سير العمل',
            'ratings' => 'التقييمات',
            'points' => 'النقاط',
            'reports' => 'التقارير',
            'github' => 'جيت هب',
            'admin' => 'الإدارة',
            default => $this->group,
        };
    }
}
