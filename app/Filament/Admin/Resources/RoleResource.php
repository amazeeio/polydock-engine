<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Admin\Resources\RoleResource\Pages\EditRole;
use App\Filament\Admin\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Admin\Resources\RoleResource\Pages\ViewRole;
use App\Models\Role;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use BezhanSalleh\FilamentShield\Support\Utils;
use BezhanSalleh\FilamentShield\Traits\HasShieldFormComponents;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoleResource extends Resource implements HasShieldPermissions
{
    use HasShieldFormComponents;

    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'shield/roles';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label('Machine ID')
                                    ->helperText('Unique identifier used in code. Cannot be changed after creation.')
                                    ->required()
                                    ->maxLength(255)
                                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                                    ->unique(
                                        table: 'roles',
                                        column: 'name',
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn ($rule, $get) => $rule->where('guard_name', $get('guard_name') ?? Utils::getFilamentAuthGuard()),
                                    ),

                                TextInput::make('label')
                                    ->label('Display Name')
                                    ->helperText('Human-readable name for this role. Can be changed freely.')
                                    ->maxLength(255),

                                TextInput::make('guard_name')
                                    ->label(__('filament-shield::filament-shield.field.guard_name'))
                                    ->default(Utils::getFilamentAuthGuard())
                                    ->nullable()
                                    ->maxLength(255)
                                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),

                                static::getSelectAllFormComponent(),
                            ])
                            ->columns([
                                'sm' => 2,
                                'lg' => 3,
                            ]),
                    ]),
                static::getShieldFormComponents(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Machine ID')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Display Name')
                    ->getStateUsing(fn ($record): string => $record->display_name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->badge()
                    ->color('warning')
                    ->label(__('filament-shield::filament-shield.column.guard_name')),
                TextColumn::make('permissions_count')
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.permissions'))
                    ->counts('permissions')
                    ->colors(['success']),
                TextColumn::make('updated_at')
                    ->label(__('filament-shield::filament-shield.column.updated_at'))
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn ($record): bool => in_array($record->name, ['super_admin', 'service-account'])),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn ($record): bool => ! in_array($record->name, ['super_admin', 'service-account']),
            )
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return strval(static::getEloquentQuery()->count());
    }
}
