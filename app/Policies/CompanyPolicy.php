<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('companies.manage');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('companies.manage');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.manage');
    }

    /**
     * F01: a company that has tickets must not be deletable — its history would
     * go with it. Retiring it with is_active = false is the intended path.
     * The tickets check lands in phase 2 when the table exists.
     */
    public function delete(User $user, Company $company): Response
    {
        if (! $user->hasPermission('companies.manage')) {
            return Response::deny('ليس لديك صلاحية لحذف الشركات.');
        }

        if ($company->contacts()->exists()) {
            return Response::deny('الشركة دي عليها جهات اتصال. امسحهم الأول أو أوقف الشركة بدل ما تحذفها.');
        }

        return Response::allow();
    }
}
