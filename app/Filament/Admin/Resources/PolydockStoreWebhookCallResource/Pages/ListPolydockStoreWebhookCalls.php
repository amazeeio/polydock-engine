<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreWebhookCallResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreWebhookCallResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPolydockStoreWebhookCalls extends ListRecords
{
    protected static string $resource = PolydockStoreWebhookCallResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
