<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "This person is not required to press «خلصت» — on the tickets they share with
 * that person." F07
 *
 * One row is one pair. A null counterpart_user_id is the wildcard: waived with
 * everyone, everywhere.
 *
 * @see \App\Services\TicketWorkflowService::completionOptional()
 */
class WorklogCompletionWaiver extends Model
{
    public const CACHE_KEY = 'worklog.completion.waivers';

    protected $fillable = ['user_id', 'counterpart_user_id'];

    protected static function booted(): void
    {
        // Read on every status transition, written a handful of times a year —
        // the same trade as Role::permissionMap() and User::overrideMap(), and
        // busted the same way (§ 4.7).
        $bust = fn () => Cache::forget(self::CACHE_KEY);

        static::saved($bust);
        static::deleted($bust);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function counterpart(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterpart_user_id');
    }

    /**
     * user id => ['all' => bool, 'with' => [counterpart ids]].
     *
     * The whole table in one cached array rather than a lookup per work log:
     * it holds only exceptions, so it stays small however many users exist, and
     * it is consulted inside a loop over a ticket's work logs.
     *
     * @return array<int, array{all: bool, with: array<int, int>}>
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $rows = DB::table('worklog_completion_waivers')
                ->get(['user_id', 'counterpart_user_id']);

            $map = [];

            foreach ($rows as $row) {
                $entry = $map[$row->user_id] ??= ['all' => false, 'with' => []];

                if ($row->counterpart_user_id === null) {
                    $entry['all'] = true;
                } else {
                    $entry['with'][] = (int) $row->counterpart_user_id;
                }

                $map[$row->user_id] = $entry;
            }

            return $map;
        });
    }

    /** Always write through here, or the cache above outlives the change. */
    public static function syncFor(int $userId, bool $withEveryone, array $counterpartIds): void
    {
        DB::transaction(function () use ($userId, $withEveryone, $counterpartIds) {
            static::where('user_id', $userId)->delete();

            // "Everyone" makes the named rows meaningless — keeping both would
            // leave a list on screen that has no effect on anything.
            $rows = $withEveryone
                ? [null]
                : collect($counterpartIds)->map(fn ($id) => (int) $id)
                    ->reject(fn ($id) => $id === $userId || $id <= 0)
                    ->unique()
                    ->values()
                    ->all();

            foreach ($rows as $counterpartId) {
                static::create(['user_id' => $userId, 'counterpart_user_id' => $counterpartId]);
            }
        });

        Cache::forget(self::CACHE_KEY);
    }
}
