<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;
use App\Policies\Concerns\DeniesInactiveUsers;

/**
 * A notification belongs to the manager who receives it in their inbox. Only
 * that user may read or mark it; admins and other managers cannot see it.
 */
class NotificationPolicy
{
    use DeniesInactiveUsers;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCompanyManager();
    }

    public function view(User $user, Notification $notification): bool
    {
        return (int) $notification->user_id === (int) $user->id;
    }

    public function update(User $user, Notification $notification): bool
    {
        return (int) $notification->user_id === (int) $user->id;
    }
}
