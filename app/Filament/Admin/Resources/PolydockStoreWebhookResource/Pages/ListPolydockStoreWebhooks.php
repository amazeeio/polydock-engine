<?php

namespace App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreWebhookResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPolydockStoreWebhooks extends ListRecords
{
    protected static string $resource = PolydockStoreWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
