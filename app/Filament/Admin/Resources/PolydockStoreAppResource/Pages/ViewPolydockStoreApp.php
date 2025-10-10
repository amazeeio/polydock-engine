<?php

namespace App\Filament\Admin\Resources\PolydockStoreAppResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreAppResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPolydockStoreApp extends ViewRecord
{
    protected static string $resource = PolydockStoreAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
