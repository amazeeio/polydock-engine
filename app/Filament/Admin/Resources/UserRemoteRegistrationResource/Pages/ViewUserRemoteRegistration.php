<?php

namespace App\Filament\Admin\Resources\UserRemoteRegistrationResource\Pages;

use App\Filament\Admin\Resources\UserRemoteRegistrationResource;
use App\Models\UserRemoteRegistration;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;

class ViewUserRemoteRegistration extends ViewRecord
{
    protected static string $resource = UserRemoteRegistrationResource::class;

    // Remove any header actions (including edit button)
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Registration Details')
                    ->schema([
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
                                TextEntry::make('created_at')
                                    ->label('Requested')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('User Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('email'),
                                TextEntry::make('user.name')
                                    ->label('Full Name'),
                                TextEntry::make('userGroup.name')
                                    ->label('Group'),
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
                                    ->url(fn ($record) 
                                        => route('filament.admin.resources.polydock-app-instances.view', 
                                            ['record' => $record->appInstance])),
                            ]),
                    ]),

                Section::make('Request Data')
                    ->schema(function ($record) {
                        return self::getRenderedSafeDataForRecord($record);
                    })
                    ->collapsible(),
            ]);
    }

    public static function getRenderedSafeDataForRecord(UserRemoteRegistration $record) : array
    {
        $safeData = $record->request_data;
        $renderedArray = [];
        foreach ($safeData as $key => $value) {
            $renderKey = "request_data_" . $key;
            $renderedItem = \Filament\Infolists\Components\TextEntry::make($renderKey)
            ->label($key)
            ->markdown()
            ->columnSpanFull()
            ->bulleted();

            if(is_array($value)) {
                $renderedItem->state($value);
            } else {
                $renderedItem->state([$value]);
            }

            $renderedArray[] = $renderedItem;   
        }
        return $renderedArray;
    }
} 