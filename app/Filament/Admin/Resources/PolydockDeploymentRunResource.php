<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use App\Filament\Admin\Resources\PolydockDeploymentRunResource\Pages;
use App\Models\PolydockDeploymentRun;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PolydockDeploymentRunResource extends Resource
{
    protected static ?string $model = PolydockDeploymentRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Deployments';

    protected static ?int $navigationSort = 150;

    #[\Override]
    public static function canViewAny(): bool
    {
        return PolydockDeploymentRun::currentUserCanManage();
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function canEdit($record): bool
    {
        return false;
    }

    #[\Override]
    public static function canDelete($record): bool
    {
        return false;
    }

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('storeApp');
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('storeApp.name')
                    ->label('App')
                    ->placeholder('Mixed')
                    ->searchable(),
                TextColumn::make('trigger_source')
                    ->badge()
                    ->label('Trigger'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('total_count')
                    ->label('Total')
                    ->numeric(),
                TextColumn::make('success_count')
                    ->label('OK')
                    ->numeric()
                    ->color('success'),
                TextColumn::make('failed_count')
                    ->label('Failed')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('lagoon_bulk_id')
                    ->label('Bulk ID')
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PolydockDeploymentRunStatusEnum::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                        ->all()),
                SelectFilter::make('trigger_source')
                    ->options(collect(PolydockDeploymentRunTriggerSourceEnum::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                        ->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    #[\Override]
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Deployment Run')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->label('UUID')->copyable(),
                        TextEntry::make('trigger_source')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('storeApp.name')->label('App')->placeholder('Mixed'),
                        TextEntry::make('triggeredByUser.email')->label('Triggered by')->placeholder('System'),
                        TextEntry::make('lagoon_bulk_id')->label('Lagoon bulk id')->copyable()->placeholder('—'),
                        TextEntry::make('total_count')->label('Total'),
                        TextEntry::make('success_count')->label('Succeeded'),
                        TextEntry::make('failed_count')->label('Failed'),
                        TextEntry::make('started_at')->dateTime(),
                        TextEntry::make('completed_at')->dateTime()->placeholder('—'),
                        TextEntry::make('poll_attempts')->label('Poll attempts'),
                    ]),
                ]),
            Section::make('Instances')
                ->schema([
                    TextEntry::make('instances_summary')
                        ->hiddenLabel()
                        ->state(fn (PolydockDeploymentRun $record) => $record->instances()
                            ->get(['name', 'last_deployment_status'])
                            ->map(fn ($i) => $i->name.' — '.($i->last_deployment_status ?? 'pending'))
                            ->implode("\n"))
                        ->placeholder('No instances attached'),
                ]),
        ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPolydockDeploymentRuns::route('/'),
            'view' => Pages\ViewPolydockDeploymentRun::route('/{record}'),
        ];
    }
}
