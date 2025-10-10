<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserRemoteRegistrationResource\Pages;
use App\Models\UserRemoteRegistration;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserRemoteRegistrationResource extends Resource
{
    protected static ?string $model = UserRemoteRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Users';

    protected static ?string $navigationLabel = 'Remote Registrations';

    protected static ?int $navigationSort = 3;

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
                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state): string => $state ? $state->getColor() : 'gray')
                    ->icon(fn ($state): string => $state ? $state->getIcon() : '')
                    ->sortable(),
                TextColumn::make('email'),
                TextColumn::make('user.name'),
                TextColumn::make('userGroup.name'),
                TextColumn::make('storeApp.store.name'),
                TextColumn::make('storeApp.name'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserRemoteRegistrations::route('/'),
            'view' => Pages\ViewUserRemoteRegistration::route('/{record}'),
        ];
    }
}
