<?php

namespace App\Filament\Admin\Resources\UserRemoteRegistrationResource\Pages;

use App\Filament\Admin\Resources\UserRemoteRegistrationResource;
use App\Models\UserRemoteRegistration;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUserRemoteRegistration extends ViewRecord
{
    protected static string $resource = UserRemoteRegistrationResource::class;

    // Remove any header actions (including edit button)
    protected function getHeaderActions(): array
    {
        return [];
    }

    #[\Override]
    public function infolist(Infolist $infolist): Infolist
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
                                    ->url(fn ($record) => route(
                                        'filament.admin.resources.polydock-app-instances.view',
                                        ['record' => $record->appInstance],
                                    )),
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
            if ($record->shouldFilterKey($key, $sensitiveKeys)) {
                $value = 'REDACTED';
            }

            $renderKey = 'request_data_'.$key;
            $renderedItem = \Filament\Infolists\Components\TextEntry::make($renderKey)
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
