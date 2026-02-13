<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreWebhookCallResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreWebhookCallResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPolydockStoreWebhookCall extends EditRecord
{
    protected static string $resource = PolydockStoreWebhookCallResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
