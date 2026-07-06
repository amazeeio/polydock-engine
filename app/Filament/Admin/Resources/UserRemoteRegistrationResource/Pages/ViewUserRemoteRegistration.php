<?php

namespace App\Filament\Admin\Resources\UserRemoteRegistrationResource\Pages;

use App\Filament\Admin\Resources\UserRemoteRegistrationResource;
use App\Models\UserRemoteRegistration;
use App\Support\SensitiveDataRedactor;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewUserRemoteRegistration extends ViewRecord
{
    protected static string $resource = UserRemoteRegistrationResource::class;

    // Remove any header actions (including edit button)
    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }

    #[Override]
    public function infolist(Schema $schema): Schema
    {
        return $infolist
            ->schema([
                Section::make('Registration Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Full Name'),
                                TextEntry::make('userGroup.name')
                                    ->label('Group'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state): string => match ($state->value) {
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'success' => 'success',
                                        'failed' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('type')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? $state->getColor() : 'gray')
                                    ->icon(fn ($state): string => $state ? $state->getIcon() : ''),
                                TextEntry::make('created_at')
                                    ->label('Requested')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('App Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('storeApp.store.name')
                                    ->label('Store'),
                                TextEntry::make('storeApp.name')
                                    ->label('App'),
                                TextEntry::make('appInstance.name')
                                    ->label('Instance')
                                    ->url(fn ($record) => $record->appInstance
                                        ? route(
                                            'filament.admin.resources.polydock-app-instances.view',
                                            ['record' => $record->appInstance],
                                        )
                                        : null),
                            ]),
                    ]),

                Section::make('Request Data')
                    ->schema(fn ($record) => self::getRenderedSafeRequestDataForRecord($record))
                    ->collapsible(),

                Section::make('Result Data')
                    ->schema(fn ($record) => self::getRenderedSafeResultDataForRecord($record))
                    ->collapsible(),
            ]);
    }

    public static function getRenderedSafeRequestDataForRecord(UserRemoteRegistration $record): array
    {
        $fullSafeData = $record->request_data ?? [];

        return self::getRenderedSafeDataForRecord($record, $fullSafeData);
    }

    public static function getRenderedSafeResultDataForRecord(UserRemoteRegistration $record): array
    {
        $fullSafeData = $record->result_data ?? [];

        return self::getRenderedSafeDataForRecord($record, $fullSafeData);
    }

    public static function getRenderedSafeDataForRecord(UserRemoteRegistration $record, $safeData): array
    {
        $sensitiveKeys = $record->getSensitiveDataKeys();

        $renderedArray = [];
        foreach ($safeData as $key => $value) {
            if (SensitiveDataRedactor::shouldRedactKey((string) $key, $sensitiveKeys)) {
                $value = SensitiveDataRedactor::REDACTED_VALUE;
            }

            if ($value === null) {
                $value = 'N/A';
            }

            $renderKey = 'request_data_'.$key;
            $renderedItem = TextEntry::make($renderKey)
                ->label($key)
                ->markdown()
                ->columnSpanFull()
                ->bulleted();

            if (is_array($value)) {
                $renderedItem->state($value);
            } else {
                $renderedItem->state([$value]);
            }

            $renderedArray[] = $renderedItem;
        }

        return $renderedArray;
    }
}
