<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages\CreatePolydockStoreWebhook;
use App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages\EditPolydockStoreWebhook;
use App\Filament\Admin\Resources\PolydockStoreWebhookResource\Pages\ListPolydockStoreWebhooks;
use App\Models\PolydockStore;
use App\Models\PolydockStoreWebhook;
use BackedEnum;
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
use UnitEnum;

class PolydockStoreWebhookResource extends Resource
{
    protected static ?string $model = PolydockStoreWebhook::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static string|UnitEnum|null $navigationGroup = 'Apps';

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
                    ->url()
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! PolydockStoreWebhook::isAllowedUrl((string) $value)) {
                            $fail('Webhook URLs must use https:// (http:// is only allowed for localhost).');
                        }
                    })
                    ->maxLength(255)
                    ->columnSpanFull(),
                Toggle::make('active')
                    ->required(),
                Toggle::make('include_sensitive_data')
                    ->label('Include sensitive data')
                    ->helperText('Send generated app credentials and raw registration data in payloads. Only enable for trusted consumers that need them (e.g. trial emails) — leave off for event logging.'),
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
                IconColumn::make('include_sensitive_data')
                    ->label('Sensitive data')
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
