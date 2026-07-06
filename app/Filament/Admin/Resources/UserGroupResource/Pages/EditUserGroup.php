<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserGroupResource\Pages;

use App\Filament\Admin\Resources\UserGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserGroup extends EditRecord
{
    protected static string $resource = UserGroupResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
