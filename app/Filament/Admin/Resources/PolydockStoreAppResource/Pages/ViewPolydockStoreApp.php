<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreAppResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreAppResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPolydockStoreApp extends ViewRecord
{
    protected static string $resource = PolydockStoreAppResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    #[\Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load custom field values from app_config JSON column
        // Fields are stored without prefix, so we load them directly with their original names
        $appConfig = $this->record->app_config ?? [];

        foreach ($appConfig as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }
}
