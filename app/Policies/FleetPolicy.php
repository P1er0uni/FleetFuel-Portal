<?php

namespace App\Policies;

use App\Models\Fleet;
use App\Models\User;
use App\Policies\Concerns\DeniesInactiveUsers;

class FleetPolicy
{
    use DeniesInactiveUsers;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCompanyManager();
    }

    public function view(User $user, Fleet $fleet): bool
    {
        return $user->isAdmin() || $user->managesCompany($fleet->company_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCompanyManager();
    }

    /** Rules and member assignment are fleet updates too. */
    public function update(User $user, Fleet $fleet): bool
    {
        return $user->isAdmin() || $user->managesCompany($fleet->company_id);
    }

    public function delete(User $user, Fleet $fleet): bool
    {
        return $user->isAdmin() || $user->managesCompany($fleet->company_id);
    }
}
