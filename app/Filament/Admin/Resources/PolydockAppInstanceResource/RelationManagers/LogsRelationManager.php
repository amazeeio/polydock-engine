<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime('d-m-Y H:i:s'),
                TextColumn::make('message')
                    ->description(fn ($record) => json_encode($record->data, JSON_PRETTY_PRINT))
                    ->wrap(),
                TextColumn::make('type'),
                TextColumn::make('level')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'error' => 'danger',
                        'warning' => 'warning',
                        'info' => 'info',
                        'debug' => 'gray',
                    }),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
