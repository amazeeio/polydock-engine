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
        if ($user->can('update_user')) {
            return true;
        }

        return $user->getKey() === $model->getKey();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('delete_user');
    }
}
