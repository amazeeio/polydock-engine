<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreWebhookResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPolydockStoreWebhooks extends ListRecords
{
    protected static string $resource = PolydockStoreWebhookResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
