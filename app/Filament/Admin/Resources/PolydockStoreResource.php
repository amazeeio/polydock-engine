<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PolydockStoreStatusEnum;
use App\Filament\Admin\Resources\PolydockStoreResource\Pages;
use App\Models\PolydockStore;
use App\PolydockEngine\Helpers\AmazeeAiBackendHelper;
use App\PolydockEngine\Helpers\LagoonHelper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PolydockStoreResource extends Resource
{
    protected static ?string $model = PolydockStore::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Stores';

    protected static ?int $navigationSort = 5000;

    #[\Override]
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
                    ->maxLength(255)
                    ->dehydrated(
                        fn (?PolydockStore $record) => ! $record || ! $record->apps()->whereHas('instances')->exists(),
                    )
                    ->disabled(
                        fn (?PolydockStore $record) => $record && $record->apps()->whereHas('instances')->exists(),
                    ),
                Forms\Components\TextInput::make('lagoon_deploy_project_prefix')
                    ->required()
                    ->maxLength(255)
                    ->dehydrated(
                        fn (?PolydockStore $record) => ! $record || ! $record->apps()->whereHas('instances')->exists(),
                    )
                    ->disabled(
                        fn (?PolydockStore $record) => $record && $record->apps()->whereHas('instances')->exists(),
                    ),
                Forms\Components\TextInput::make('lagoon_deploy_organization_id_ext')
                    ->required()
                    ->maxLength(255)
                    ->dehydrated(
                        fn (?PolydockStore $record) => ! $record || ! $record->apps()->whereHas('instances')->exists(),
                    )
                    ->disabled(
                        fn (?PolydockStore $record) => $record && $record->apps()->whereHas('instances')->exists(),
                    ),
                Forms\Components\TextInput::make('amazee_ai_backend_region_id_ext')
                    ->numeric()
                    ->dehydrated(
                        fn (?PolydockStore $record) => ! $record || ! $record->apps()->whereHas('instances')->exists(),
                    )
                    ->disabled(
                        fn (?PolydockStore $record) => $record && $record->apps()->whereHas('instances')->exists(),
                    ),
                Forms\Components\Textarea::make('lagoon_deploy_private_key')
                    ->columnSpanFull()
                    ->rows(10)
                    ->formatStateUsing(fn ($state) => null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->placeholder(fn ($record) => filled($record?->lagoon_deploy_private_key) 
                        ? 'Current key is set. Leave empty to keep it, or enter a new one to replace it.' 
                        : 'No key is currently set. Enter a new key here.'
                    ),
            ]);
    }

    #[\Override]
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
                    ->formatStateUsing(fn ($state) => LagoonHelper::getLagoonCodeDataValueForRegion($state, 'name'))
                    ->label('Deploy Region')
                    ->searchable(),
                Tables\Columns\TextColumn::make('lagoon_deploy_project_prefix')
                    ->label('Project Prefix')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amazee_ai_backend_region_id_ext')
                    ->label('AI Region')
                    ->formatStateUsing(
                        fn ($state) => AmazeeAiBackendHelper::getAmazeeAiBackendCodeDataValueForRegion($state, 'name'),
                    )
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (PolydockStore $record): bool => $record->apps()->whereHas('instances')->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->hidden(fn () => true), // Disable bulk delete entirely
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
            'index' => Pages\ListPolydockStores::route('/'),
            'create' => Pages\CreatePolydockStore::route('/create'),
            'view' => Pages\ViewPolydockStore::route('/{record}'),
            'edit' => Pages\EditPolydockStore::route('/{record}/edit'),
        ];
    }

    #[\Override]
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Store Details')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('name')
                                    ->label('Store Name'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state) => match ($state->value) {
                                        PolydockStoreStatusEnum::PUBLIC->value => 'success',
                                        PolydockStoreStatusEnum::PRIVATE->value => 'warning',
                                        default => 'gray',
                                    }),
                            ]),
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar')
                                    ->iconColor('gray'),
                                \Filament\Infolists\Components\IconEntry::make('listed_in_marketplace')
                                    ->label('Listed in Marketplace')
                                    ->boolean(),
                            ]),
                    ])
                    ->columnSpan(2),

                \Filament\Infolists\Components\Section::make('Deployment Configuration')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('lagoon_deploy_region_id_ext')
                                    ->label('Deploy Region')
                                    ->formatStateUsing(
                                        fn ($state) => LagoonHelper::getLagoonCodeDataValueForRegion($state, 'name'),
                                    ),
                                \Filament\Infolists\Components\TextEntry::make('amazee_ai_backend_region_id_ext')
                                    ->label('AI Backend Region')
                                    ->formatStateUsing(
                                        fn ($state) => AmazeeAiBackendHelper::getAmazeeAiBackendCodeDataValueForRegion(
                                            $state,
                                            'name',
                                        ),
                                    ),
                            ]),
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('lagoon_deploy_project_prefix')
                                    ->label('Project Prefix')
                                    ->icon('heroicon-m-code-bracket')
                                    ->iconColor('success'),
                                \Filament\Infolists\Components\TextEntry::make('lagoon_deploy_organization_id_ext')
                                    ->label('Deploy Organization')
                                    ->icon('heroicon-m-building-office')
                                    ->iconColor('warning'),
                            ]),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }
}
