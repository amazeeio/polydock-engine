<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_user');
    }

    public function view(User $user, User $model): bool
    {
        if ($user->can('view_user')) {
            return true;
        }

        return $user->getKey() === $model->getKey();
    }

    public function create(User $user): bool
    {
        return $user->can('create_user');
    }

    public function update(User $user, User $model): bool
    {
        if ($user->getKey() === $model->getKey()) {
            return true;
        }

        // Only a super_admin (allowed by Gate::before) may change another super_admin.
        return ! $model->hasRole('super_admin') && $user->can('update_user');
    }

    public function delete(User $user, User $model): bool
    {
        return ! $model->hasRole('super_admin') && $user->can('delete_user');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_user');
    }
}
