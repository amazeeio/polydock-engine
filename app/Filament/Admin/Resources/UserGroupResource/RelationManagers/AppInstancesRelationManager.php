<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserGroupResource\RelationManagers;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AppInstancesRelationManager extends RelationManager
{
    protected static string $relationship = 'appInstances';

    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->url(fn ($record): string => PolydockAppInstanceResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                TextColumn::make('storeApp.name')
                    ->label('Store App')
                    ->searchable(),
                // The status enum implements HasColor/HasIcon/HasLabel, so
                // badge() resolves color, icon, and label by itself.
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ViewAction::make()
                    ->url(fn ($record): string => PolydockAppInstanceResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
