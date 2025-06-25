<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PolydockStore;
use Illuminate\Auth\Access\HandlesAuthorization;

class PolydockStorePolicy
{
    use HandlesAuthorization;

    public function delete(User $user, PolydockStore $polydockStore): bool
    {
        // Check if any store apps have instances
        return !$polydockStore->apps()
            ->whereHas('instances')
            ->exists();
    }
} 