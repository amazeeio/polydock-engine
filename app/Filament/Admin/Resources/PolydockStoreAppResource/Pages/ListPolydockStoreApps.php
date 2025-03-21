<?php

namespace App\Filament\Admin\Resources\PolydockStoreAppResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreAppResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPolydockStoreApps extends ListRecords
{
    protected static string $resource = PolydockStoreAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
