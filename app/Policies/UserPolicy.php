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

    /**
     * ★ (2026-08-24) This used to refuse everybody, for a good reason: users are
     * referenced by tickets, work logs and the points ledger, and deleting one
     * broke that history. Deletion is allowed now because it no longer does —
     * it is a soft delete that leaves every row and every name in place (see
     * App\Models\User and UserDeletionService).
     *
     * Whether the person can be deleted YET is a different question, and not one
     * a policy can answer: it depends on whether they still hold live work.
     * UserDeletionService::blockers owns that and refuses with the specifics.
     */
    public function delete(User $user, User $target): Response
    {
        if (! $user->hasPermission('users.manage')) {
            return Response::deny('ليس لديك صلاحية لحذف المستخدمين.');
        }

        if ($user->is($target)) {
            return Response::deny('مينفعش تحذف حسابك انت.');
        }

        return Response::allow();
    }

    /** Undoing a delete needs the same authority as making one. */
    public function restore(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage') && ! $user->is($target);
    }
}
