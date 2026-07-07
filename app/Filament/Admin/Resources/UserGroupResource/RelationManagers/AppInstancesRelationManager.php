<?php

namespace App\Filament\Admin\Resources\UserGroupResource\RelationManagers;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
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
                    ->url(fn ($record) => PolydockAppInstanceResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                TextColumn::make('storeApp.name')
                    ->label('Store App')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getColor())
                    ->icon(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getIcon())
                    ->formatStateUsing(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getLabel()),
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
                    ->url(fn ($record) => PolydockAppInstanceResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
