<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreWebhookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPolydockStoreWebhooks extends ListRecords
{
    protected static string $resource = PolydockStoreWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
