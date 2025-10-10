<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockStoreWebhookCallResource\Pages;
use App\Models\PolydockStoreWebhookCall;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PolydockStoreWebhookCallResource extends Resource
{
    protected static ?string $model = PolydockStoreWebhookCall::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Webhook Calls';

    protected static ?int $navigationSort = 5200;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('webhook.store.name')
                    ->description(fn (PolydockStoreWebhookCall $record) => $record->webhook->url)
                    ->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->description(fn (PolydockStoreWebhookCall $record) => $record->response_code)
                    ->badge()
                    ->color(fn ($state) => match ($state->getLabel()) {
                        'Success' => 'success',
                        'Failed' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('attempt')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Call Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('webhook.store.name')
                                    ->label('Store')
                                    ->icon('heroicon-m-building-storefront')
                                    ->columnSpan(1),
                                Infolists\Components\TextEntry::make('event')
                                    ->icon('heroicon-m-bell')
                                    ->columnSpan(1),
                                Infolists\Components\TextEntry::make('webhook.url')
                                    ->label('URL')
                                    ->url(fn ($state) => $state)
                                    ->openUrlInNewTab()
                                    ->icon('heroicon-m-globe-alt')
                                    ->columnSpanFull(),
                            ]),
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state) => match ($state->getLabel()) {
                                        'Success' => 'success',
                                        'Failed' => 'danger',
                                        default => 'warning',
                                    }),
                                Infolists\Components\TextEntry::make('response_code')
                                    ->label('Response Code')
                                    ->icon('heroicon-m-signal')
                                    ->color(fn ($state) => str_starts_with($state, '2') ? 'success' : 'danger'),
                                Infolists\Components\TextEntry::make('attempt')
                                    ->icon('heroicon-m-arrow-path'),
                                Infolists\Components\TextEntry::make('processed_at')
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar'),
                            ]),
                    ])
                    ->columnSpan(3),

                Infolists\Components\Section::make('Payload & Response')
                    ->schema([
                        Infolists\Components\TextEntry::make('payload')
                            ->label('Request Payload')
                            ->state(function ($record) {
                                return json_encode($record->payload, JSON_PRETTY_PRINT);
                            })
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('response_body')
                            ->label('Response Body')
                            ->visible(fn ($state) => ! empty($state))
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('exception')
                            ->label('Error Details')
                            ->visible(fn ($state) => ! empty($state))
                            ->color('danger')
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(3),
            ])
            ->columns(3);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPolydockStoreWebhookCalls::route('/'),
            'view' => Pages\ViewPolydockStoreWebhookCall::route('/{record}'),
        ];
    }
}
