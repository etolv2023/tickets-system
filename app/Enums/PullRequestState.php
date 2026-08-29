<?php

namespace App\Enums;

/**
 * Where a pull request ended up — F27.
 *
 * GitHub models this as state=open|closed plus a separate merged_at, so
 * "merged" and "closed without merging" arrive as the same state. They are the
 * opposite outcome from where this system sits, so the distinction is resolved
 * once, at sync time, and stored.
 */
enum PullRequestState: string
{
    case Open = 'open';

    case Merged = 'merged';

    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مفتوح',
            self::Merged => 'اتدمج',
            self::Closed => 'اتقفل من غير دمج',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Merged => 'green',
            self::Closed => 'slate',
        };
    }

    /** GitHub's two fields, resolved into one. */
    public static function fromGitHub(string $state, ?string $mergedAt): self
    {
        return match (true) {
            $mergedAt !== null => self::Merged,
            $state === 'open' => self::Open,
            default => self::Closed,
        };
    }
}
