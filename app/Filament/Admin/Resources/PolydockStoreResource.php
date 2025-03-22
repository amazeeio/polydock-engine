<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PolydockStoreStatusEnum;
use App\Filament\Admin\Resources\PolydockStoreResource\Pages;
use App\Filament\Admin\Resources\PolydockStoreResource\RelationManagers;
use App\Models\PolydockStore;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PolydockStoreResource extends Resource
{
    protected static ?string $model = PolydockStore::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Stores';

    protected static ?int $navigationSort = 5000;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options(PolydockStoreStatusEnum::class)
                    ->required(),
                Forms\Components\Toggle::make('listed_in_marketplace')
                    ->required(),
                Forms\Components\TextInput::make('lagoon_deploy_region_id_ext')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('lagoon_deploy_project_prefix')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('lagoon_deploy_private_key')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('amazee_ai_backend_region_id_ext')
                    ->numeric(),
                Forms\Components\TextInput::make('lagoon_deploy_organization_id_ext')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\IconColumn::make('listed_in_marketplace')
                    ->label('Listed')
                    ->boolean(),
                Tables\Columns\TextColumn::make('lagoon_deploy_region_id_ext')
                    ->label('Deploy Region')
                    ->searchable(),
                Tables\Columns\TextColumn::make('lagoon_deploy_project_prefix')
                    ->label('Project Prefix')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amazee_ai_backend_region_id_ext')
                    ->label('AI Region')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lagoon_deploy_organization_id_ext')
                    ->label('Deploy Org')
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
            'index' => Pages\ListPolydockStores::route('/'),
            'create' => Pages\CreatePolydockStore::route('/create'),
            'edit' => Pages\EditPolydockStore::route('/{record}/edit'),
        ];
    }
}
