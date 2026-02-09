<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserGroupResource\Pages;
use App\Filament\Admin\Resources\UserGroupResource\RelationManagers;
use App\Models\UserGroup;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserGroupResource extends Resource
{
    protected static ?string $model = UserGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Users';

    protected static ?string $navigationLabel = 'Groups';

    protected static ?int $navigationSort = 2;

    #[\Override]
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            RelationManagers\UsersRelationManager::class,
            RelationManagers\AppInstancesRelationManager::class,
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserGroups::route('/'),
            'create' => Pages\CreateUserGroup::route('/create'),
            'view' => Pages\ViewUserGroup::route('/{record}'),
            'edit' => Pages\EditUserGroup::route('/{record}/edit'),
        ];
    }

    #[\Override]
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Group Details')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('name')
                                    ->label('Group Name')
                                    ->icon('heroicon-m-user-group')
                                    ->iconColor('primary'),
                                \Filament\Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar')
                                    ->iconColor('gray'),
                            ]),
                        \Filament\Infolists\Components\TextEntry::make('users_count')
                            ->label('Total Users')
                            ->state(fn ($record) => $record->users()->count())
                            ->icon('heroicon-m-users')
                            ->iconColor('success'),
                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('owners_count')
                                    ->label('Number of Owners')
                                    ->state(fn ($record) => $record->owners()->count())
                                    ->icon('heroicon-m-user-group')
                                    ->iconColor('success'),
                                \Filament\Infolists\Components\TextEntry::make('members_count')
                                    ->label('Number of Members')
                                    ->state(fn ($record) => $record->members()->count())
                                    ->icon('heroicon-m-user-group')
                                    ->iconColor('success'),
                                \Filament\Infolists\Components\TextEntry::make('viewers_count')
                                    ->label('Number of Viewers')
                                    ->state(fn ($record) => $record->viewers()->count())
                                    ->icon('heroicon-m-eye')
                                    ->iconColor('success'),
                            ]),
                    ])
                    ->columnSpan(2),

                \Filament\Infolists\Components\Section::make('App Instances')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('polydock_app_instances_count')
                                    ->label('Count')
                                    ->state(fn ($record) => $record->appInstances()->count())
                                    ->icon('heroicon-m-squares-2x2')
                                    ->iconColor('warning'),
                                \Filament\Infolists\Components\TextEntry::make('app_instances_stage_create_count')
                                    ->label('Stage Create')
                                    ->state(fn ($record) => $record->appInstancesStageCreate()->count())
                                    ->icon('heroicon-m-plus-circle')
                                    ->iconColor('info'),
                                \Filament\Infolists\Components\TextEntry::make('app_instances_stage_deploy_count')
                                    ->label('Stage Deploy')
                                    ->state(fn ($record) => $record->appInstancesStageDeploy()->count())
                                    ->icon('heroicon-m-rocket-launch')
                                    ->iconColor('success'),
                                \Filament\Infolists\Components\TextEntry::make('app_instances_stage_upgrade_count')
                                    ->label('Stage Upgrade')
                                    ->state(fn ($record) => $record->appInstancesStageUpgrade()->count())
                                    ->icon('heroicon-m-arrow-up-circle')
                                    ->iconColor('primary'),
                                \Filament\Infolists\Components\TextEntry::make('app_instances_stage_remove_count')
                                    ->label('Stage Remove')
                                    ->state(fn ($record) => $record->appInstancesStageRemove()->count())
                                    ->icon('heroicon-m-trash')
                                    ->iconColor('danger'),
                                \Filament\Infolists\Components\TextEntry::make('app_instances_stage_running_count')
                                    ->label('Stage Running')
                                    ->state(fn ($record) => $record->appInstancesStageRunning()->count())
                                    ->icon('heroicon-m-play-circle')
                                    ->iconColor('success'),
                                \Filament\Infolists\Components\TextEntry::make('app_instances_stage_failed_count')
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
