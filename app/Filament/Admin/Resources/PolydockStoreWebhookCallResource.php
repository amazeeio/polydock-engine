<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockStoreWebhookCallResource\Pages\ListPolydockStoreWebhookCalls;
use App\Filament\Admin\Resources\PolydockStoreWebhookCallResource\Pages\ViewPolydockStoreWebhookCall;
use App\Models\PolydockStoreWebhookCall;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PolydockStoreWebhookCallResource extends Resource
{
    protected static ?string $model = PolydockStoreWebhookCall::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|UnitEnum|null $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Webhook Calls';

    protected static ?int $navigationSort = 5200;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('webhook.store.name')
                    ->description(fn (PolydockStoreWebhookCall $record) => $record->webhook->url)
                    ->sortable(),
                TextColumn::make('event')
                    ->searchable(),
                TextColumn::make('status')
                    ->description(fn (PolydockStoreWebhookCall $record) => $record->response_code)
                    ->badge()
                    ->color(fn ($state) => match ($state->getLabel()) {
                        'Success' => 'success',
                        'Failed' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('attempt')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Call Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('webhook.store.name')
                                    ->label('Store')
                                    ->icon('heroicon-m-building-storefront')
                                    ->columnSpan(1),
                                TextEntry::make('event')
                                    ->icon('heroicon-m-bell')
                                    ->columnSpan(1),
                                TextEntry::make('webhook.url')
                                    ->label('URL')
                                    ->url(fn ($state) => $state)
                                    ->openUrlInNewTab()
                                    ->icon('heroicon-m-globe-alt')
                                    ->columnSpanFull(),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state) => match ($state->getLabel()) {
                                        'Success' => 'success',
                                        'Failed' => 'danger',
                                        default => 'warning',
                                    }),
                                TextEntry::make('response_code')
                                    ->label('Response Code')
                                    ->icon('heroicon-m-signal')
                                    ->color(fn ($state) => str_starts_with((string) $state, '2')
                                        ? 'success'
                                        : 'danger'),
                                TextEntry::make('attempt')
                                    ->icon('heroicon-m-arrow-path'),
                                TextEntry::make('processed_at')
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar'),
                            ]),
                    ])
                    ->columnSpan(3),

                Section::make('Payload & Response')
                    ->schema([
                        TextEntry::make('payload')
                            ->label('Request Payload')
                            ->state(fn ($record) => json_encode($record->payload, JSON_PRETTY_PRINT))
                            ->columnSpanFull(),
                        TextEntry::make('response_body')
                            ->label('Response Body')
                            ->visible(fn ($state) => ! empty($state))
                            ->columnSpanFull(),
                        TextEntry::make('exception')
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
            'index' => ListPolydockStoreWebhookCalls::route('/'),
            'view' => ViewPolydockStoreWebhookCall::route('/{record}'),
        ];
    }
}
