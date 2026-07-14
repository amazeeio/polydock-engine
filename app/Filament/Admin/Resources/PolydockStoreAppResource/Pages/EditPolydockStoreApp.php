<?php

namespace App\Filament\Admin\Resources\PolydockStoreAppResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreAppResource;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Attributes\PolydockAppStoreFields;
use App\Services\PolydockAppClassDiscovery;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

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
        $data['lagoon_auto_idle'] = $appConfig['lagoon_auto_idle'] ?? 0;
        $data['lagoon_production_environment'] = $appConfig['lagoon_production_environment'] ?? 'main';
        $data['refresh_unallocated_instances'] = $appConfig['refresh_unallocated_instances'] ?? false;
        $data['refresh_unallocated_instances_after_days'] = $appConfig['refresh_unallocated_instances_after_days'] ?? 7;
        $data['project_naming_mode'] = $appConfig['project_naming_mode'] ?? PolydockStoreApp::PROJECT_NAMING_MODE_PATTERN;
        $data['project_naming_adjectives'] = $appConfig['project_naming_adjectives'] ?? [];
        $data['project_naming_nouns'] = $appConfig['project_naming_nouns'] ?? [];
        $data['lagoon_custom_route_enabled'] = $appConfig['lagoon_custom_route_enabled'] ?? false;
        $data['lagoon_custom_route_domain_pattern'] = $appConfig['lagoon_custom_route_domain_pattern'] ?? '';
        $data['lagoon_custom_route_service'] = $appConfig['lagoon_custom_route_service'] ?? '';
        $data['lagoon_custom_route_annotations'] = $appConfig['lagoon_custom_route_annotations'] ?? [];

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

        // Always persist these runtime settings in app_config for app-instance defaults.
        $existingAppConfig = $this->record->app_config ?? [];
        $appConfig['lagoon_auto_idle'] = isset($data['lagoon_auto_idle'])
            ? (int) $data['lagoon_auto_idle']
            : (int) ($existingAppConfig['lagoon_auto_idle'] ?? 0);
        $appConfig['lagoon_production_environment'] = (string) (
            $data['lagoon_production_environment']
            ?? $existingAppConfig['lagoon_production_environment']
            ?? 'main'
        );
        $appConfig['refresh_unallocated_instances'] = (bool) (
            $data['refresh_unallocated_instances']
            ?? $existingAppConfig['refresh_unallocated_instances']
            ?? false
        );
        $appConfig['refresh_unallocated_instances_after_days'] = max(
            1,
            (int) (
                $data['refresh_unallocated_instances_after_days']
                ?? $existingAppConfig['refresh_unallocated_instances_after_days']
                ?? 7
            ),
        );
        $appConfig['project_naming_mode'] = (string) (
            $data['project_naming_mode']
            ?? $existingAppConfig['project_naming_mode']
            ?? PolydockStoreApp::PROJECT_NAMING_MODE_PATTERN
        );
        $appConfig['project_naming_adjectives'] = array_values((array) (
            $data['project_naming_adjectives']
            ?? $existingAppConfig['project_naming_adjectives']
            ?? []
        ));
        $appConfig['project_naming_nouns'] = array_values((array) (
            $data['project_naming_nouns']
            ?? $existingAppConfig['project_naming_nouns']
            ?? []
        ));
        $appConfig['lagoon_custom_route_enabled'] = (bool) (
            $data['lagoon_custom_route_enabled']
            ?? $existingAppConfig['lagoon_custom_route_enabled']
            ?? false
        );
        $appConfig['lagoon_custom_route_domain_pattern'] = (string) (
            $data['lagoon_custom_route_domain_pattern']
            ?? $existingAppConfig['lagoon_custom_route_domain_pattern']
            ?? ''
        );
        $appConfig['lagoon_custom_route_service'] = (string) (
            $data['lagoon_custom_route_service']
            ?? $existingAppConfig['lagoon_custom_route_service']
            ?? ''
        );
        $appConfig['lagoon_custom_route_annotations'] = (array) (
            $data['lagoon_custom_route_annotations']
            ?? $existingAppConfig['lagoon_custom_route_annotations']
            ?? []
        );
        unset(
            $data['lagoon_auto_idle'],
            $data['lagoon_production_environment'],
            $data['refresh_unallocated_instances'],
            $data['refresh_unallocated_instances_after_days'],
            $data['project_naming_mode'],
            $data['project_naming_adjectives'],
            $data['project_naming_nouns'],
            $data['lagoon_custom_route_enabled'],
            $data['lagoon_custom_route_domain_pattern'],
            $data['lagoon_custom_route_service'],
            $data['lagoon_custom_route_annotations'],
        );

        // Store the app config as JSON
        $data['app_config'] = ! empty($appConfig) ? $appConfig : null;

        return $data;
    }
}
