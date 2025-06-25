<?php

namespace App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreWebhookResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPolydockStoreWebhook extends EditRecord
{
    protected static string $resource = PolydockStoreWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
