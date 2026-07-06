<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\RelationManagers\ActivitiesRelationManager;
use App\Filament\Admin\Resources\UserGroupResource\Pages\CreateUserGroup;
use App\Filament\Admin\Resources\UserGroupResource\Pages\EditUserGroup;
use App\Filament\Admin\Resources\UserGroupResource\Pages\ListUserGroups;
use App\Filament\Admin\Resources\UserGroupResource\Pages\ViewUserGroup;
use App\Filament\Admin\Resources\UserGroupResource\RelationManagers\AppInstancesRelationManager;
use App\Filament\Admin\Resources\UserGroupResource\RelationManagers\UsersRelationManager;
use App\Models\User;
use App\Models\UserGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserGroupResource extends Resource
{
    protected static ?string $model = UserGroup::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    protected static ?string $navigationLabel = 'Groups';

    protected static ?int $navigationSort = 2;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_beta')
                    ->label('Beta group')
                    ->helperText('Beta groups receive redeploys on the shorter beta cadence, where an app defines one.'),
            ]);
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var User|null $user */
        $user = auth()->user();
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('super_admin') || $user->can('view_any_user_group')) {
            return $query;
        }

        return $query->whereIn('id', $user->groups()->select('user_groups.id'));
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->searchable()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->sortable(),
                IconColumn::make('is_beta')
                    ->label('Beta')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
            AppInstancesRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListUserGroups::route('/'),
            'create' => CreateUserGroup::route('/create'),
            'view' => ViewUserGroup::route('/{record}'),
            'edit' => EditUserGroup::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Group Name')
                                    ->icon('heroicon-m-user-group')
                                    ->iconColor('primary'),
                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar')
                                    ->iconColor('gray'),
                            ]),
                        TextEntry::make('users_count')
                            ->label('Total Users')
                            ->state(fn ($record) => $record->users()->count())
                            ->icon('heroicon-m-users')
                            ->iconColor('success'),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('owners_count')
                                    ->label('Number of Owners')
                                    ->state(fn ($record) => $record->owners()->count())
                                    ->icon('heroicon-m-user-group')
                                    ->iconColor('success'),
                                TextEntry::make('members_count')
                                    ->label('Number of Members')
                                    ->state(fn ($record) => $record->members()->count())
                                    ->icon('heroicon-m-user-group')
                                    ->iconColor('success'),
                                TextEntry::make('viewers_count')
                                    ->label('Number of Viewers')
                                    ->state(fn ($record) => $record->viewers()->count())
                                    ->icon('heroicon-m-eye')
                                    ->iconColor('success'),
                            ]),
                    ])
                    ->columnSpan(2),

                Section::make('App Instances')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('polydock_app_instances_count')
                                    ->label('Count')
                                    ->state(fn ($record) => $record->appInstances()->count())
                                    ->icon('heroicon-m-squares-2x2')
                                    ->iconColor('warning'),
                                TextEntry::make('app_instances_stage_create_count')
                                    ->label('Stage Create')
                                    ->state(fn ($record) => $record->appInstancesStageCreate()->count())
                                    ->icon('heroicon-m-plus-circle')
                                    ->iconColor('info'),
                                TextEntry::make('app_instances_stage_deploy_count')
                                    ->label('Stage Deploy')
                                    ->state(fn ($record) => $record->appInstancesStageDeploy()->count())
                                    ->icon('heroicon-m-rocket-launch')
                                    ->iconColor('success'),
                                TextEntry::make('app_instances_stage_upgrade_count')
                                    ->label('Stage Upgrade')
                                    ->state(fn ($record) => $record->appInstancesStageUpgrade()->count())
                                    ->icon('heroicon-m-arrow-up-circle')
                                    ->iconColor('primary'),
                                TextEntry::make('app_instances_stage_remove_count')
                                    ->label('Stage Remove')
                                    ->state(fn ($record) => $record->appInstancesStageRemove()->count())
                                    ->icon('heroicon-m-trash')
                                    ->iconColor('danger'),
                                TextEntry::make('app_instances_stage_running_count')
                                    ->label('Stage Running')
                                    ->state(fn ($record) => $record->appInstancesStageRunning()->count())
                                    ->icon('heroicon-m-play-circle')
                                    ->iconColor('success'),
                                TextEntry::make('app_instances_stage_failed_count')
                                    ->label('Stage Failed')
                                    ->state(fn ($record) => $record->appInstancesFailed()->count())
                                    ->icon('heroicon-m-x-circle')
                                    ->iconColor('danger'),
                            ]),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }
}
