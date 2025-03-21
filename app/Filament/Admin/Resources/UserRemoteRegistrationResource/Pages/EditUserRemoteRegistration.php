<?php

namespace App\Filament\Admin\Resources\UserRemoteRegistrationResource\Pages;

use App\Filament\Admin\Resources\UserRemoteRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserRemoteRegistration extends EditRecord
{
    protected static string $resource = UserRemoteRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
