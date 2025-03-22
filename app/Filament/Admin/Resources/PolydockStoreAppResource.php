<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockStoreAppResource\Pages;
use App\Filament\Admin\Resources\PolydockStoreAppResource\RelationManagers;
use App\Models\PolydockStoreApp;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PolydockStoreAppResource extends Resource
{
    protected static ?string $model = PolydockStoreApp::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Apps';

    protected static ?int $navigationSort = 5100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('uuid')
                    ->label('UUID')
                    ->required()
                    ->maxLength(36),
                Forms\Components\TextInput::make('polydock_store_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('polydock_app_class')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('author')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('website')
                    ->maxLength(255),
                Forms\Components\TextInput::make('support_email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('lagoon_deploy_git')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('lagoon_deploy_branch')
                    ->required()
                    ->maxLength(255)
                    ->default('main'),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\Toggle::make('available_for_trials')
                    ->required(),
                Forms\Components\TextInput::make('target_unallocated_app_instances')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\IconColumn::make('available_for_trials')
                    ->label('Trials')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('target_unallocated_app_instances')
                    ->label('Unallocated')
                    ->state(function($record) {
                        return $record->unallocated_instances_count . "/"  . $record->target_unallocated_app_instances;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('allocatedInstances')
                    ->state(function($record) {
                        return $record->allocatedInstances()->count(); 
                    })
                    ->label('Allocated')
                    ->numeric()
                    ->sortable(),
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
            'index' => Pages\ListPolydockStoreApps::route('/'),
            'create' => Pages\CreatePolydockStoreApp::route('/create'),
            'edit' => Pages\EditPolydockStoreApp::route('/{record}/edit'),
        ];
    }
}
