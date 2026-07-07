<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ApiTokenResource\Pages\ListApiTokens;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;
use UnitEnum;

class ApiTokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Users';

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?int $navigationSort = 120;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tokenable_type', User::class);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tokenable.email')
                    ->label('Owner')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abilities')
                    ->formatStateUsing(
                        static fn (mixed $state): string => is_array($state) ? implode(', ', $state) : (string) $state
                    )
                    ->label('Abilities')
                    ->wrap(),
                TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Revoke'),
            ])
            ->toolbarActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiTokens::route('/'),
        ];
    }
}
