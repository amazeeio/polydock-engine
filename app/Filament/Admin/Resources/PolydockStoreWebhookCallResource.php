<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockStoreWebhookCallResource\Pages;
use App\Filament\Admin\Resources\PolydockStoreWebhookCallResource\RelationManagers;
use App\Models\PolydockStoreWebhookCall;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PolydockStoreWebhookCallResource extends Resource
{
    protected static ?string $model = PolydockStoreWebhookCall::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Webhook Calls';

    protected static ?int $navigationSort = 5200;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('polydock_store_webhook_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('event')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('payload')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('attempt')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\DateTimePicker::make('processed_at'),
                Forms\Components\TextInput::make('response_code')
                    ->maxLength(255),
                Forms\Components\Textarea::make('response_body')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('exception')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('polydock_store_webhook_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('attempt')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('response_code')
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
            'index' => Pages\ListPolydockStoreWebhookCalls::route('/'),
            'create' => Pages\CreatePolydockStoreWebhookCall::route('/create'),
            'edit' => Pages\EditPolydockStoreWebhookCall::route('/{record}/edit'),
        ];
    }
}
