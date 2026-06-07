<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ActivityLogResource\Pages;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $modelLabel = 'Audit Entry';

    protected static ?string $pluralModelLabel = 'Audit Log';

    protected static ?int $navigationSort = 9000;

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.email')
                    ->label('Actor')
                    ->searchable()
                    ->description(fn (Activity $record) => $record->properties['is_service_account'] ?? false
                        ? 'service-account'
                        : null)
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->searchable()
                    ->wrap()
                    ->limit(80),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Resource')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Resource Type')
                    ->options(fn () => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
                        ->all()),
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                SelectFilter::make('is_service_account')
                    ->label('Actor Type')
                    ->options([
                        'human' => 'Human',
                        'service' => 'Service Account',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'service') {
                            return $query->whereJsonContains('properties', ['is_service_account' => true]);
                        }
                        if ($data['value'] === 'human') {
                            return $query->where(function ($q) {
                                $q->whereJsonContains('properties', ['is_service_account' => false])
                                    ->orWhereJsonDoesntContainKey('properties->is_service_account');
                            });
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    #[\Override]
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Activity Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Timestamp')
                                    ->dateTime('Y-m-d H:i:s'),
                                Infolists\Components\TextEntry::make('causer.email')
                                    ->label('Actor')
                                    ->placeholder('System'),
                                Infolists\Components\TextEntry::make('description')
                                    ->label('Action'),
                            ]),
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('subject_type')
                                    ->label('Resource Type')
                                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),
                                Infolists\Components\TextEntry::make('subject_id')
                                    ->label('Resource ID')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('event')
                                    ->badge()
                                    ->color(fn (?string $state) => match ($state) {
                                        'created' => 'success',
                                        'updated' => 'warning',
                                        'deleted' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),
                Infolists\Components\Section::make('Context')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('properties.ip')
                                    ->label('IP Address')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('properties.token_name')
                                    ->label('Token')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('properties.is_service_account')
                                    ->label('Service Account')
                                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'warning' : 'gray'),
                                Infolists\Components\TextEntry::make('properties.user_agent')
                                    ->label('User Agent')
                                    ->placeholder('-')
                                    ->limit(60),
                            ]),
                    ]),
                Infolists\Components\Section::make('Changes')
                    ->schema([
                        Infolists\Components\TextEntry::make('properties.old')
                            ->label('Before')
                            ->state(fn (Activity $record) => isset($record->properties['old'])
                                ? json_encode($record->properties['old'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                                : null)
                            ->placeholder('N/A')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('properties.attributes')
                            ->label('After')
                            ->state(fn (Activity $record) => isset($record->properties['attributes'])
                                ? json_encode($record->properties['attributes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                                : null)
                            ->placeholder('N/A')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Activity $record) => isset($record->properties['old']) || isset($record->properties['attributes'])),
                Infolists\Components\Section::make('Full Properties')
                    ->schema([
                        Infolists\Components\TextEntry::make('properties')
                            ->label('')
                            ->state(fn (Activity $record) => json_encode(
                                collect($record->properties)->except(['old', 'attributes', 'ip', 'user_agent', 'token_id', 'token_name', 'is_service_account', 'request_id'])->all(),
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                            ))
                            ->placeholder('No additional properties')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Activity $record) => collect($record->properties)
                        ->except(['old', 'attributes', 'ip', 'user_agent', 'token_id', 'token_name', 'is_service_account', 'request_id'])
                        ->isNotEmpty()),
            ]);
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
