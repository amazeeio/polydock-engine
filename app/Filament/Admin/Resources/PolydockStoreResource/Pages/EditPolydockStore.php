<?php

namespace App\Filament\Admin\Resources\PolydockStoreResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPolydockStore extends EditRecord
{
    protected static string $resource = PolydockStoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(
                    fn () => $this->record
                        ->apps()
                        ->whereHas('instances')
                        ->exists(),
                ),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
