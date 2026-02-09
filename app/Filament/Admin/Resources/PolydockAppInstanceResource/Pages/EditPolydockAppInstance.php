<?php

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use Filament\Resources\Pages\EditRecord;

class EditPolydockAppInstance extends EditRecord
{
    protected static string $resource = PolydockAppInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Remove DeleteAction
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
