<?php

namespace App\Filament\Admin\Resources\UserGroupResource\RelationManagers;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AppInstancesRelationManager extends RelationManager
{
    protected static string $relationship = 'appInstances';

    #[\Override]
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->url(fn ($record) => PolydockAppInstanceResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('storeApp.name')
                    ->label('Store App')
                    ->searchable(),
                // The status enum implements HasColor/HasIcon/HasLabel, so
                // badge() resolves color, icon, and label by itself.
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => PolydockAppInstanceResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
