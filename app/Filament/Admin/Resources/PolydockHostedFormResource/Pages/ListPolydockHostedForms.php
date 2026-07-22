<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockHostedFormResource\Pages;

use App\Filament\Admin\Resources\PolydockHostedFormResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPolydockHostedForms extends ListRecords
{
    protected static string $resource = PolydockHostedFormResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
