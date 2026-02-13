<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserRemoteRegistrationResource\Pages;

use App\Filament\Admin\Resources\UserRemoteRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserRemoteRegistrations extends ListRecords
{
    protected static string $resource = UserRemoteRegistrationResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
