<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PolydockStoreStatusEnum;
use App\Filament\Admin\Resources\PolydockStoreResource\Pages\CreatePolydockStore;
use App\Filament\Admin\Resources\PolydockStoreResource\Pages\EditPolydockStore;
use App\Filament\Admin\Resources\PolydockStoreResource\Pages\ListPolydockStores;
use App\Filament\Admin\Resources\PolydockStoreResource\Pages\ViewPolydockStore;
use App\Models\PolydockStore;
use App\PolydockEngine\Helpers\AmazeeAiBackendHelper;
use App\PolydockEngine\Helpers\LagoonHelper;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PolydockStoreResource extends Resource
{
    protected static ?string $model = PolydockStore::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Stores';

    protected static ?int $navigationSort = 5000;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->options(PolydockStoreStatusEnum::class)
                    ->required(),
                Toggle::make('listed_in_marketplace')
                    ->label('Listed in Marketplace')
                    ->required(),
                TextInput::make('lagoon_deploy_region_id_ext')
                    ->label('Lagoon Deploy Region ID')
                    ->required()
                    ->maxLength(255)
                    ->dehydrated(
                        fn (?PolydockStore $record) => ! $record || ! $record->apps()->whereHas('instances')->exists(),
                    )
                    ->disabled(
                        fn (?PolydockStore $record) => $record && $record->apps()->whereHas('instances')->exists(),
                    ),
                TextInput::make('lagoon_deploy_project_prefix')
                    ->label('Lagoon Deploy Project Prefix')
                    ->required()
                    ->maxLength(255)
                    ->dehydrated(
                        fn (?PolydockStore $record) => ! $record || ! $record->apps()->whereHas('instances')->exists(),
                    )
                    ->disabled(
                        fn (?PolydockStore $record) => $record && $record->apps()->whereHas('instances')->exists(),
                    ),
                TextInput::make('lagoon_deploy_organization_id_ext')
                    ->label('Lagoon Deploy Organization ID')
                    ->required()
                    ->maxLength(255)
                    ->dehydrated(
                        fn (?PolydockStore $record) => ! $record || ! $record->apps()->whereHas('instances')->exists(),
                    )
                    ->disabled(
                        fn (?PolydockStore $record) => $record && $record->apps()->whereHas('instances')->exists(),
                    ),
                TextInput::make('amazee_ai_backend_region_id_ext')
                    ->label('amazee.ai Backend Region ID')
                    ->numeric()
                    ->dehydrated(
                        fn (?PolydockStore $record) => ! $record || ! $record->apps()->whereHas('instances')->exists(),
                    )
                    ->disabled(
                        fn (?PolydockStore $record) => $record && $record->apps()->whereHas('instances')->exists(),
                    ),
                TextInput::make('lagoon_deploy_group_name')
                    ->label('Lagoon Deploy Group Name')
                    ->required()
                    ->maxLength(255)
                    ->dehydrated(
                        fn (?PolydockStore $record) => ! $record || ! $record->apps()->whereHas('instances')->exists(),
                    )
                    ->disabled(
                        fn (?PolydockStore $record) => $record && $record->apps()->whereHas('instances')->exists(),
                    ),
                Textarea::make('lagoon_deploy_private_key')
                    ->label('Lagoon Deploy Private Key')
                    ->columnSpanFull()
                    ->rows(3)
                    ->formatStateUsing(fn ($state) => null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->placeholder(fn ($record) => filled($record?->lagoon_deploy_private_key)
                        ? 'Current key is set. Leave empty to keep it, or enter a new one to replace it.'
                        : 'No key is currently set. Enter a new key here.'
                    )
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, ?PolydockStore $record) {
                        $keyToUse = filled($state) ? $state : $record?->lagoon_deploy_private_key;
                        $set('derived_public_key', $keyToUse ? LagoonHelper::getPublicKeyFromPrivateKey($keyToUse) : null);
                    })
                    ->rules([
                        fn () => function (string $attribute, $value, Closure $fail) {
                            if (filled($value) && ! LagoonHelper::getPublicKeyFromPrivateKey($value)) {
                                $fail('The private key is invalid.');
                            }
                        },
                    ]),
                Textarea::make('derived_public_key')
                    ->label('Lagoon Deploy Public Key')
                    ->helperText('This is the public key derived from the stored private key (or the one you just entered).')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull()
                    ->rows(5)
                    ->formatStateUsing(fn (?PolydockStore $record) => $record?->lagoon_deploy_private_key
                            ? LagoonHelper::getPublicKeyFromPrivateKey($record->lagoon_deploy_private_key)
                            : null
                    ),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchable()
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status'),
                IconColumn::make('listed_in_marketplace')
                    ->label('Listed')
                    ->boolean(),
                TextColumn::make('lagoon_deploy_region_id_ext')
                    ->formatStateUsing(fn ($state) => LagoonHelper::getLagoonCodeDataValueForRegion($state, 'name'))
                    ->label('Deploy Region')
                    ->searchable(),
                TextColumn::make('lagoon_deploy_project_prefix')
                    ->label('Project Prefix')
                    ->searchable(),
                TextColumn::make('amazee_ai_backend_region_id_ext')
                    ->label('AI Region')
                    ->formatStateUsing(
                        fn ($state) => AmazeeAiBackendHelper::getAmazeeAiBackendCodeDataValueForRegion($state, 'name'),
                    )
                    ->sortable(),
                TextColumn::make('lagoon_deploy_organization_id_ext')
                    ->label('Deploy Org')
                    ->searchable(),
                TextColumn::make('lagoon_deploy_group_name')
                    ->label('Deploy Group')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (PolydockStore $record): bool => $record->apps()->whereHas('instances')->exists()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn () => true), // Disable bulk delete entirely
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
            'index' => ListPolydockStores::route('/'),
            'create' => CreatePolydockStore::route('/create'),
            'view' => ViewPolydockStore::route('/{record}'),
            'edit' => EditPolydockStore::route('/{record}/edit'),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Store Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Store Name'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state) => match ($state->value) {
                                        PolydockStoreStatusEnum::PUBLIC->value => 'success',
                                        PolydockStoreStatusEnum::PRIVATE->value => 'warning',
                                        default => 'gray',
                                    }),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar')
                                    ->iconColor('gray'),
                                IconEntry::make('listed_in_marketplace')
                                    ->label('Listed in Marketplace')
                                    ->boolean(),
                            ]),
                    ])
                    ->columnSpan(2),

                Section::make('Deployment Configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('lagoon_deploy_region_id_ext')
                                    ->label('Deploy Region')
                                    ->formatStateUsing(
                                        fn ($state) => LagoonHelper::getLagoonCodeDataValueForRegion($state, 'name'),
                                    ),
                                TextEntry::make('amazee_ai_backend_region_id_ext')
                                    ->label('AI Backend Region')
                                    ->formatStateUsing(
                                        fn ($state) => AmazeeAiBackendHelper::getAmazeeAiBackendCodeDataValueForRegion(
                                            $state,
                                            'name',
                                        ),
                                    ),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('lagoon_deploy_project_prefix')
                                    ->label('Project Prefix')
                                    ->icon('heroicon-m-code-bracket')
                                    ->iconColor('success'),
                                TextEntry::make('lagoon_deploy_organization_id_ext')
                                    ->label('Deploy Organization')
                                    ->icon('heroicon-m-building-office')
                                    ->iconColor('warning'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('lagoon_deploy_group_name')
                                    ->label('Deploy Group Name')
                                    ->icon('heroicon-m-users')
                                    ->iconColor('primary'),
                            ]),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }
}
