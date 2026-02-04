<?php

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d-m-Y H:i:s'),
                Tables\Columns\TextColumn::make('message')
                    ->description(fn ($record) => json_encode($record->data, JSON_PRETTY_PRINT))
                    ->wrap(),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'error' => 'danger',
                        'warning' => 'warning',
                        'info' => 'info',
                        'debug' => 'gray',
                    }),
            ])
            ->filters([
            ])
            ->headerActions([
            ])
            ->actions([
            ])
            ->bulkActions([
            ]);
    }
}
