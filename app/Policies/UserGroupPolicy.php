<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserGroupRoleEnum;
use App\Models\User;
use App\Models\UserGroup;

class UserGroupPolicy
{
    public function viewAny(User $user): bool
    {
        // Any authenticated user may list groups; the controller scopes results to their own data.
        return true;
    }

    public function view(User $user, UserGroup $group): bool
    {
        if ($user->hasRole('service-account')) {
            return true;
        }

        return $user->can('view_user_group') || $user->groups()->whereKey($group->getKey())->exists();
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('service-account')) {
            return true;
        }

        // Anyone who can authenticate into the app can create a group.
        return true;
    }

    public function update(User $user, UserGroup $group): bool
    {
        if ($user->hasRole('service-account')) {
            return false;
        }

        if ($user->can('update_user_group')) {
            return true;
        }

        return $user->hasGroupRoleAtLeast($group, UserGroupRoleEnum::ADMIN);
    }

    public function delete(User $user, UserGroup $group): bool
    {
        if ($user->hasRole('service-account')) {
            return false;
        }

        if ($user->can('delete_user_group')) {
            return true;
        }

        return $user->hasGroupRoleAtLeast($group, UserGroupRoleEnum::OWNER);
    }

    public function manageMembers(User $user, UserGroup $group): bool
    {
        if ($user->hasRole('service-account')) {
            return false;
        }

        // No separate permission yet; treat this as an update-level capability.
        return $this->update($user, $group);
    }
}
