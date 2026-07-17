<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage');
    }

    /**
     * Locking yourself out is the one mistake an admin can't undo from the UI,
     * so both self-destructive paths are refused here rather than warned about.
     */
    public function deactivate(User $user, User $target): Response
    {
        if (! $user->hasPermission('users.manage')) {
            return Response::deny('ليس لديك صلاحية لإدارة المستخدمين.');
        }

        if ($user->is($target)) {
            return Response::deny('مينفعش توقف حسابك انت.');
        }

        return Response::allow();
    }

    public function delete(User $user, User $target): Response
    {
        if (! $user->hasPermission('users.manage')) {
            return Response::deny('ليس لديك صلاحية لحذف المستخدمين.');
        }

        if ($user->is($target)) {
            return Response::deny('مينفعش تحذف حسابك انت.');
        }

        // Users are referenced by tickets, work logs and the points ledger from
        // phase 2 on. Deactivating keeps that history readable; deleting breaks it.
        return Response::deny('المستخدمين مبيتحذفوش — أوقف الحساب بدل كده عشان تاريخه ميضيعش.');
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage') && ! $user->is($target);
    }
}
