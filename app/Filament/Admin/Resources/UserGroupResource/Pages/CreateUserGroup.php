<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserGroupResource\Pages;

use App\Filament\Admin\Resources\UserGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserGroup extends CreateRecord
{
    protected static string $resource = UserGroupResource::class;
}
