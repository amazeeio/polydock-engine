<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages\CreatePolydockStoreWebhook;
use App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages\EditPolydockStoreWebhook;
use App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages\ListPolydockStoreWebhooks;
use App\Models\PolydockStore;
use App\Models\PolydockStoreWebhook;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PolydockStoreWebhookResource extends Resource
{
    protected static ?string $model = PolydockStoreWebhook::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static string|\UnitEnum|null $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Webhooks';

    protected static ?int $navigationSort = 5100;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('polydock_store_id')
                    ->label('Store')
                    ->options(PolydockStore::all()->pluck('name', 'id'))
                    ->required(),
                TextInput::make('url')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Toggle::make('active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('url')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListPolydockStoreWebhooks::route('/'),
            'create' => CreatePolydockStoreWebhook::route('/create'),
            'edit' => EditPolydockStoreWebhook::route('/{record}/edit'),
        ];
    }
}
