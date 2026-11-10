<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Deny-all policy for panel resources that only super_admin may manage.
 *
 * super_admin never reaches these methods (Gate::before allows it first).
 * Filament allows any action whose policy or policy method is missing, so
 * every method Filament checks is spelled out here.
 */
class SuperAdminOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Model $record): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Model $record): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
