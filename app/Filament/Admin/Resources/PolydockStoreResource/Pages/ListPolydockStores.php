<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPolydockStores extends ListRecords
{
    protected static string $resource = PolydockStoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
