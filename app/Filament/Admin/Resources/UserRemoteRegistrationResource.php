<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserRemoteRegistrationResource\Pages\ListUserRemoteRegistrations;
use App\Filament\Admin\Resources\UserRemoteRegistrationResource\Pages\ViewUserRemoteRegistration;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserRemoteRegistration;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserRemoteRegistrationResource extends Resource
{
    protected static ?string $model = UserRemoteRegistration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    protected static ?string $navigationLabel = 'Remote Registrations';

    protected static ?int $navigationSort = 3;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->searchable()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state): string => $state ? $state->getColor() : 'gray')
                    ->icon(fn ($state): string => $state ? $state->getIcon() : '')
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('userGroup.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('storeApp.store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('storeApp.name')
                    ->label('Store App')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->options(fn () => PolydockStore::pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        return $query->when($data['value'], fn ($query) => $query->whereHas('storeApp', fn ($q) => $q->where('polydock_store_id', $data['value'])));
                    }),
                SelectFilter::make('store_app_id')
                    ->label('Store App')
                    ->options(fn () => PolydockStoreApp::pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        return $query->when($data['value'], fn ($query) => $query->where('polydock_store_app_id', $data['value']));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    #[Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[Override]
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListUserRemoteRegistrations::route('/'),
            'view' => ViewUserRemoteRegistration::route('/{record}'),
        ];
    }
}
