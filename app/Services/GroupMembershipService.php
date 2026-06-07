<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserGroupRoleEnum;
use App\Models\User;
use App\Models\UserGroup;

class GroupMembershipService
{
    /**
     * Add a user to a group with a given role, logging the action.
     *
     * If the user is already a member, this updates their role instead
     * and logs a role_changed event rather than a misleading member_added.
     */
    public function addUserToGroup(User $user, UserGroup $group, UserGroupRoleEnum $role, ?User $actor = null): void
    {
        $existingMember = $group->users()->whereKey($user->id)->first();

        if ($existingMember !== null) {
            /** @var string $previousRole */
            $previousRole = $existingMember->pivot->getAttribute('role');

            if ($previousRole === $role->value) {
                // Already a member with the same role -- no-op.
                return;
            }

            $group->users()->updateExistingPivot($user->id, ['role' => $role->value]);

            activity('audit')
                ->performedOn($group)
                ->causedBy($actor ?? auth()->user())
                ->withProperties([
                    'action' => 'group.member_role_changed',
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'previous_role' => $previousRole,
                    'new_role' => $role->value,
                ])
                ->log("User '{$user->email}' role changed from '{$previousRole}' to '{$role->value}'");

            return;
        }

        $group->users()->attach($user->id, ['role' => $role->value]);

        activity('audit')
            ->performedOn($group)
            ->causedBy($actor ?? auth()->user())
            ->withProperties([
                'action' => 'group.member_added',
                'user_id' => $user->id,
                'user_email' => $user->email,
                'role' => $role->value,
            ])
            ->log("User '{$user->email}' added to group with role '{$role->value}'");
    }

    /**
     * Remove a user from a group, logging the action.
     *
     * No-ops if the user is not a member of the group.
     */
    public function removeUserFromGroup(User $user, UserGroup $group, ?User $actor = null): void
    {
        $existingUser = $group->users()
            ->wherePivot('user_id', $user->id)
            ->first();

        if ($existingUser === null) {
            return;
        }

        /** @var string $previousRole */
        $previousRole = $existingUser->pivot->getAttribute('role');

        $group->users()->detach($user->id);

        activity('audit')
            ->performedOn($group)
            ->causedBy($actor ?? auth()->user())
            ->withProperties([
                'action' => 'group.member_removed',
                'user_id' => $user->id,
                'user_email' => $user->email,
                'previous_role' => $previousRole,
            ])
            ->log("User '{$user->email}' removed from group");
    }

    /**
     * Change a user's role within a group, logging the action.
     *
     * No-ops if the user is not a member of the group or already has the target role.
     */
    public function changeUserRole(User $user, UserGroup $group, UserGroupRoleEnum $newRole, ?User $actor = null): void
    {
        $existingUser = $group->users()
            ->wherePivot('user_id', $user->id)
            ->first();

        if ($existingUser === null) {
            return;
        }

        /** @var string $previousRole */
        $previousRole = $existingUser->pivot->getAttribute('role');

        if ($previousRole === $newRole->value) {
            return;
        }

        $group->users()->updateExistingPivot($user->id, ['role' => $newRole->value]);

        activity('audit')
            ->performedOn($group)
            ->causedBy($actor ?? auth()->user())
            ->withProperties([
                'action' => 'group.member_role_changed',
                'user_id' => $user->id,
                'user_email' => $user->email,
                'previous_role' => $previousRole,
                'new_role' => $newRole->value,
            ])
            ->log("User '{$user->email}' role changed from '{$previousRole}' to '{$newRole->value}'");
    }
}
