<?php

namespace App\Filament\Admin\Resources\PolydockStoreAppResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreAppResource;
use App\Services\PolydockAppClassDiscovery;
use Filament\Resources\Pages\CreateRecord;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppStoreFields;

class CreatePolydockStoreApp extends CreateRecord
{
    protected static string $resource = PolydockStoreAppResource::class;

    #[\Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $discovery = app(PolydockAppClassDiscovery::class);
        $className = $data['polydock_app_class'] ?? null;

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

        // Always persist these runtime settings in app_config for app-instance defaults.
        $appConfig['lagoon_auto_idle'] = isset($data['lagoon_auto_idle']) ? (int) $data['lagoon_auto_idle'] : 0;
        $appConfig['lagoon_production_environment'] = (string) ($data['lagoon_production_environment'] ?? 'main');
        unset($data['lagoon_auto_idle'], $data['lagoon_production_environment']);

        // Store the app config as JSON
        $data['app_config'] = ! empty($appConfig) ? $appConfig : null;

        return $data;
    }
}
