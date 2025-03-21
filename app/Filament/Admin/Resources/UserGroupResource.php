<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserGroupResource\Pages;
use App\Filament\Admin\Resources\UserGroupResource\RelationManagers;
use App\Models\UserGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;

class UserGroupResource extends Resource
{
    protected static ?string $model = UserGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Users';

    protected static ?string $navigationLabel = 'Groups';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

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

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserGroups::route('/'),
            'create' => Pages\CreateUserGroup::route('/create'),
            'view' => Pages\ViewUserGroup::route('/{record}'),
            'edit' => Pages\EditUserGroup::route('/{record}/edit'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Group Details')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('Group Name')
                            ->icon('heroicon-m-user-group')
                            ->iconColor('primary'),
                        \Filament\Infolists\Components\TextEntry::make('users_count')
                            ->label('Number of Members')
                            ->state(fn ($record) => $record->users()->count())
                            ->icon('heroicon-m-users')
                            ->iconColor('success'),
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->dateTime()
                            ->icon('heroicon-m-calendar')
                            ->iconColor('gray'),
                    ])
                    ->columnSpan(2),

                \Filament\Infolists\Components\Section::make('Members')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('users.name')
                            ->label('Current Members')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->state(fn ($record) => $record->users->map(fn($user) => 
                                "{$user->first_name} {$user->last_name} ({$user->pivot->role})"
                            ))
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }
}
