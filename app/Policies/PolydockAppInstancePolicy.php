<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserGroupRoleEnum;
use App\Models\PolydockAppInstance;
use App\Models\User;
use App\Models\UserGroup;

class PolydockAppInstancePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('service-account')) {
            return true;
        }

        return $user->can('view_any_polydock_app_instance') || $user->groups()->exists();
    }

    public function view(User $user, PolydockAppInstance $instance): bool
    {
        if ($user->hasRole('service-account')) {
            return true;
        }

        if ($user->can('view_polydock_app_instance')) {
            return true;
        }

        if ($instance->user_group_id === null) {
            return false;
        }

        return $user->groups()->whereKey($instance->user_group_id)->exists();
    }

    public function create(User $user): bool
    {
        // Only used for platform-level create paths.
        return $user->can('create_polydock_app_instance');
    }

    public function createForGroup(User $user, UserGroup $group): bool
    {
        if ($user->hasRole('service-account')) {
            return true;
        }

        if ($user->can('create_polydock_app_instance')) {
            return true;
        }

        return $user->hasGroupRoleAtLeast($group, UserGroupRoleEnum::MEMBER);
    }

    public function update(User $user, PolydockAppInstance $instance): bool
    {
        if ($user->hasRole('service-account')) {
            return true;
        }

        if ($user->can('update_polydock_app_instance')) {
            return true;
        }

        $group = $instance->userGroup;
        if ($group === null) {
            return false;
        }

        return $user->hasGroupRoleAtLeast($group, UserGroupRoleEnum::MEMBER);
    }

    public function delete(User $user, PolydockAppInstance $instance): bool
    {
        if ($user->hasRole('service-account')) {
            return true;
        }

        if ($user->can('delete_polydock_app_instance')) {
            return true;
        }

        $group = $instance->userGroup;
        if ($group === null) {
            return false;
        }

        return $user->hasGroupRoleAtLeast($group, UserGroupRoleEnum::MEMBER);
    }

    public function forceDelete(User $user, PolydockAppInstance $instance): bool
    {
        if ($user->hasRole('service-account')) {
            return false;
        }

        if ($user->can('force_delete_polydock_app_instance')) {
            return true;
        }

        $group = $instance->userGroup;
        if ($group === null) {
            return false;
        }

        return $user->hasGroupRoleAtLeast($group, UserGroupRoleEnum::OWNER);
    }

    public function assignToGroup(User $user, PolydockAppInstance $instance, UserGroup $targetGroup): bool
    {
        if ($user->hasRole('service-account')) {
            return true;
        }

        if ($user->can('update_polydock_app_instance')) {
            return true;
        }

        $currentGroup = $instance->userGroup;
        if ($currentGroup === null) {
            return false;
        }

        return $user->hasGroupRoleAtLeast($currentGroup, UserGroupRoleEnum::ADMIN)
            && $user->hasGroupRoleAtLeast($targetGroup, UserGroupRoleEnum::MEMBER);
    }
}
