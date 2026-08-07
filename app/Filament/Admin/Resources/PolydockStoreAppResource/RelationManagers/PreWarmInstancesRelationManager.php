<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreAppResource\RelationManagers;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PreWarmInstancesRelationManager extends RelationManager
{
    protected static string $relationship = 'unallocatedInstances';

    protected static ?string $title = 'Pre-warm Instances';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->url(fn ($record): string => PolydockAppInstanceResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                // The status enum implements HasColor/HasIcon/HasLabel, so
                // badge() resolves color, icon, and label by itself.
                TextColumn::make('status')
                    ->badge(),
                IconColumn::make('allocation_lock')
                    ->label('Locked')
                    ->state(fn ($record): bool => filled($record->allocation_lock))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
                TextColumn::make('age')
                    ->state(fn ($record) => $record->created_at?->diffForHumans()),
                IconColumn::make('is_stale')
                    ->label('Stale')
                    ->state(fn ($record) => $record->created_at?->lte(
                        now()->subDays($this->getOwnerRecord()->refresh_unallocated_instances_after_days),
                    ))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('stale')
                    ->label('Stale')
                    ->queries(
                        true: fn ($query) => $query->where(
                            'created_at',
                            '<=',
                            now()->subDays($this->getOwnerRecord()->refresh_unallocated_instances_after_days),
                        ),
                        false: fn ($query) => $query->where(
                            'created_at',
                            '>',
                            now()->subDays($this->getOwnerRecord()->refresh_unallocated_instances_after_days),
                        ),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record): string => PolydockAppInstanceResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
