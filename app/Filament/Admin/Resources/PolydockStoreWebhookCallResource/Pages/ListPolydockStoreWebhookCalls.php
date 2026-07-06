<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreWebhookCallResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreWebhookCallResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPolydockStoreWebhookCalls extends ListRecords
{
    protected static string $resource = PolydockStoreWebhookCallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
