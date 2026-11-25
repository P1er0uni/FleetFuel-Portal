<?php

namespace App\Policies;

use App\Models\NotificationRule;
use App\Models\User;
use App\Policies\Concerns\DeniesInactiveUsers;

/**
 * Notification rules follow the fleet pattern: admins manage every company,
 * a manager only their own.
 */
class NotificationRulePolicy
{
    use DeniesInactiveUsers;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCompanyManager();
    }

    public function view(User $user, NotificationRule $rule): bool
    {
        return $user->isAdmin() || $user->managesCompany($rule->company_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCompanyManager();
    }

    public function update(User $user, NotificationRule $rule): bool
    {
        return $user->isAdmin() || $user->managesCompany($rule->company_id);
    }

    public function delete(User $user, NotificationRule $rule): bool
    {
        return $user->isAdmin() || $user->managesCompany($rule->company_id);
    }
}
