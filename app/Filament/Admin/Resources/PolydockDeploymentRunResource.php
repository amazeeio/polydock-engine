<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use App\Filament\Admin\Resources\PolydockDeploymentRunResource\Pages\ListPolydockDeploymentRuns;
use App\Filament\Admin\Resources\PolydockDeploymentRunResource\Pages\ViewPolydockDeploymentRun;
use App\Models\PolydockDeploymentRun;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PolydockDeploymentRunResource extends Resource
{
    protected static ?string $model = PolydockDeploymentRun::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static string|\UnitEnum|null $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Deployments';

    protected static ?int $navigationSort = 150;

    public static function canViewAny(): bool
    {
        return PolydockDeploymentRun::currentUserCanManage();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('storeApp');
    }

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
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
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

    public static function getPages(): array
    {
        return [
            'index' => ListPolydockDeploymentRuns::route('/'),
            'view' => ViewPolydockDeploymentRun::route('/{record}'),
        ];
    }
}
