<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Filament\Admin\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Users';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('last_name')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255)
                    ->label(fn (string $operation): string => 
                        $operation === 'create' ? 'Password' : 'New Password (leave blank to keep current)')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name'),
                TextColumn::make('last_name'),
                TextColumn::make('email'),
                TextColumn::make('groups_count')
                    ->counts('groups')
                    ->label('Groups'),
                TextColumn::make('created_at')->dateTime(),
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
            RelationManagers\GroupsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('User Details')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('first_name')
                                    ->label('First Name'),
                                \Filament\Infolists\Components\TextEntry::make('last_name')
                                    ->label('Last Name'),
                            ]),
                        \Filament\Infolists\Components\TextEntry::make('email')
                            ->icon('heroicon-m-envelope')
                            ->iconColor('primary'),
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->dateTime()
                            ->icon('heroicon-m-calendar')
                            ->iconColor('gray'),
                    ])
                    ->columnSpan(2),

                \Filament\Infolists\Components\Section::make('Group Membership')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('groups_count')
                            ->label('Number of Groups')
                            ->state(fn ($record) => $record->groups()->count())
                            ->icon('heroicon-m-user-group')
                            ->iconColor('success'),
                        \Filament\Infolists\Components\TextEntry::make('groups.name')
                            ->label('Member of')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->state(fn ($record) => $record->groups->pluck('name'))
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }
}
