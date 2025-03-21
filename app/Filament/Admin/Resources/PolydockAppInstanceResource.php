<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;
use App\Filament\Admin\Resources\PolydockAppInstanceResource\RelationManagers;
use App\Models\PolydockAppInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PolydockAppInstanceResource extends Resource
{
    protected static ?string $model = PolydockAppInstance::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'App Instances';

    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('polydock_store_app_id')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('user_group_id')
                    ->relationship('userGroup', 'name'),
                Forms\Components\TextInput::make('app_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('status'),
                Forms\Components\TextInput::make('status_message')
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('next_poll_after'),
                Forms\Components\TextInput::make('data'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('storeApp.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('userGroup.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('app_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('status_message')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPolydockAppInstances::route('/'),
            'create' => Pages\CreatePolydockAppInstance::route('/create'),
            'edit' => Pages\EditPolydockAppInstance::route('/{record}/edit'),
        ];
    }
}
