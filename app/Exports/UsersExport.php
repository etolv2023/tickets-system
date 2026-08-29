<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * F22.3 — the user list.
 *
 * Note what is not here: no password column, no hash, no remember token, and
 * no Discord id. The import side already refuses to read passwords from a
 * sheet (F02); the export side must not be the hole that puts them back on one.
 */
class UsersExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use Exportable, SanitizesCells;

    /** @param array<string, mixed> $filters */
    public function __construct(private readonly array $filters = [])
    {
    }

    public function query()
    {
        $status = $this->filters['status'] ?? null;

        // The same filter chain the screen runs — see UserController::index.
        // Deleted people stay hidden unless explicitly asked for: User carries
        // no soft-delete global scope, so this is explicit rather than automatic.
        return User::query()
            ->when($status !== 'deleted', fn ($q) => $q->present())
            ->when($status === 'deleted', fn ($q) => $q->deletedOnly())
            ->select(['id', 'name', 'email', 'role_id', 'is_active', 'must_change_password', 'last_login_at', 'daily_capacity_hours', 'deleted_at'])
            ->when($this->filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($this->filters['role'] ?? null, fn ($q, $role) => $q->where('role_id', $role))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['الاسم', 'الإيميل', 'الدور', 'السعة اليومية (س)', 'الحالة', 'لازم يغيّر الباسورد', 'آخر دخول', 'تاريخ الحذف'];
    }

    /** @param User $user */
    public function map($user): array
    {
        $tz = config('app.display_timezone');

        return $this->sanitizeRow([
            $user->name,
            $user->email,
            $user->role?->name_ar,
            (float) $user->daily_capacity_hours,
            $user->deleted_at !== null ? 'محذوف' : ($user->is_active ? 'مفعّل' : 'موقوف'),
            $user->must_change_password ? 'أيوه' : 'لأ',
            $user->last_login_at?->timezone($tz)->format('Y-m-d H:i'),
            $user->deleted_at?->timezone($tz)->format('Y-m-d H:i'),
        ]);
    }

    public function title(): string
    {
        return 'المستخدمين';
    }
}
