<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PolydockStore;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PolydockStorePolicy
{
    use HandlesAuthorization;

    public function delete(User $user, PolydockStore $polydockStore): bool
    {
        // Check if any store apps have instances
        return ! $polydockStore
            ->apps()
            ->whereHas('instances')
            ->exists();
    }
}
