<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Filament\Admin\Resources\ActivityLogResource\Pages\ViewActivityLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $modelLabel = 'Audit Entry';

    protected static ?string $pluralModelLabel = 'Audit Log';

    protected static ?int $navigationSort = 9000;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('causer.email')
                    ->label('Actor')
                    ->searchable()
                    ->description(fn (Activity $record) => $record->properties['is_service_account'] ?? false
                        ? 'service-account'
                        : null)
                    ->placeholder('System'),
                TextColumn::make('description')
                    ->label('Action')
                    ->searchable()
                    ->wrap()
                    ->limit(80),
                TextColumn::make('subject_type')
                    ->label('Resource')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('subject_id')
                    ->label('ID')
                    ->placeholder('-'),
                TextColumn::make('event')
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
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activity Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Timestamp')
                                    ->dateTime('Y-m-d H:i:s'),
                                TextEntry::make('causer.email')
                                    ->label('Actor')
                                    ->placeholder('System'),
                                TextEntry::make('description')
                                    ->label('Action'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('subject_type')
                                    ->label('Resource Type')
                                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),
                                TextEntry::make('subject_id')
                                    ->label('Resource ID')
                                    ->placeholder('-'),
                                TextEntry::make('event')
                                    ->badge()
                                    ->color(fn (?string $state) => match ($state) {
                                        'created' => 'success',
                                        'updated' => 'warning',
                                        'deleted' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),
                Section::make('Context')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('properties.ip')
                                    ->label('IP Address')
                                    ->placeholder('-'),
                                TextEntry::make('properties.token_name')
                                    ->label('Token')
                                    ->placeholder('-'),
                                TextEntry::make('properties.is_service_account')
                                    ->label('Service Account')
                                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'warning' : 'gray'),
                                TextEntry::make('properties.user_agent')
                                    ->label('User Agent')
                                    ->placeholder('-')
                                    ->limit(60),
                            ]),
                    ]),
                Section::make('Changes')
                    ->schema([
                        TextEntry::make('attribute_changes.old')
                            ->label('Before')
                            ->state(fn (Activity $record) => isset($record->attribute_changes['old'])
                                ? json_encode($record->attribute_changes['old'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                                : null)
                            ->placeholder('N/A')
                            ->columnSpanFull(),
                        TextEntry::make('attribute_changes.attributes')
                            ->label('After')
                            ->state(fn (Activity $record) => isset($record->attribute_changes['attributes'])
                                ? json_encode($record->attribute_changes['attributes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                                : null)
                            ->placeholder('N/A')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Activity $record) => isset($record->attribute_changes['old']) || isset($record->attribute_changes['attributes'])),
                Section::make('Full Properties')
                    ->schema([
                        TextEntry::make('properties')
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }
}
