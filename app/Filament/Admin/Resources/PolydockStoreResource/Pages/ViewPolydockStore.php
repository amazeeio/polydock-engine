<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPolydockStore extends ViewRecord
{
    protected static string $resource = PolydockStoreResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
