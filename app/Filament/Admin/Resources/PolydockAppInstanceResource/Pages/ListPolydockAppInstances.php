<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPolydockAppInstances extends ListRecords
{
    protected static string $resource = PolydockAppInstanceResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
