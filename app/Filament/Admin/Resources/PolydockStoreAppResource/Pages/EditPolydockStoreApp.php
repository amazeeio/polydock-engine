<?php

namespace App\Filament\Admin\Resources\PolydockStoreAppResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreAppResource;
use App\Services\PolydockAppClassDiscovery;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppStoreFields;

class EditPolydockStoreApp extends EditRecord
{
    protected static string $resource = PolydockStoreAppResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->instances()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
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

    #[\Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $discovery = app(PolydockAppClassDiscovery::class);

        // Use the record's polydock_app_class since it may not be in $data
        // (field is disabled/not dehydrated when instances exist)
        $className = $data['polydock_app_class'] ?? $this->record->polydock_app_class;

        $appConfig = [];

        if ($className) {
            // Get the prefixed field names from the schema
            $prefixedFieldNames = $discovery->getStoreAppFormFieldNames($className);
            $prefix = PolydockAppStoreFields::FIELD_PREFIX;

            // Build a list of original (unprefixed) field names
            $originalFieldNames = [];
            foreach ($prefixedFieldNames as $prefixedName) {
                if (str_starts_with($prefixedName, $prefix)) {
                    $originalFieldNames[] = substr($prefixedName, strlen($prefix));
                }
            }

            // Extract fields that match the original field names from form data
            foreach ($originalFieldNames as $fieldName) {
                if (array_key_exists($fieldName, $data)) {
                    $appConfig[$fieldName] = $data[$fieldName];
                    unset($data[$fieldName]);
                }
            }
        }

        // Store the app config as JSON
        $data['app_config'] = ! empty($appConfig) ? $appConfig : null;

        return $data;
    }
}
