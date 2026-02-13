<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserRemoteRegistrationResource\Pages;

use App\Filament\Admin\Resources\UserRemoteRegistrationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserRemoteRegistration extends CreateRecord
{
    protected static string $resource = UserRemoteRegistrationResource::class;
}
