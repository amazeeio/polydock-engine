<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserGroupResource\Pages;

use App\Filament\Admin\Resources\UserGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUserGroup extends ViewRecord
{
    protected static string $resource = UserGroupResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
