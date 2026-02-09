<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages;
use App\Models\PolydockStoreWebhook;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PolydockStoreWebhookResource extends Resource
{
    protected static ?string $model = PolydockStoreWebhook::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Webhooks';

    protected static ?int $navigationSort = 5100;

    #[\Override]
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('url')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('active')
                    ->required(),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('url')
                    ->searchable(),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
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

    #[\Override]
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPolydockStoreWebhooks::route('/'),
            'create' => Pages\CreatePolydockStoreWebhook::route('/create'),
            'edit' => Pages\EditPolydockStoreWebhook::route('/{record}/edit'),
        ];
    }
}
