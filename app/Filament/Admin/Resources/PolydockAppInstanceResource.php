<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;
use App\Filament\Admin\Resources\PolydockAppInstanceResource\RelationManagers;
use App\Models\PolydockAppInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Infolist;
use App\Enums\PolydockAppInstanceStatusForEngine;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use App\Filament\Admin\Resources\UserGroupResource;

class PolydockAppInstanceResource extends Resource
{
    protected static ?string $model = PolydockAppInstance::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'App Instances';

    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('polydock_store_app_id')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('user_group_id')
                    ->relationship('userGroup', 'name'),
                Forms\Components\TextInput::make('app_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('status'),
                Forms\Components\TextInput::make('status_message')
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('next_poll_after'),
                Forms\Components\TextInput::make('data'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('storeApp.name')
                    ->label('Store App')
                    ->searchable(),
                TextColumn::make('userGroup.name')
                    ->label('User Group')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getColor())
                    ->icon(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getIcon())
                    ->formatStateUsing(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getLabel()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Instance Details')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('name')
                                    ->label('Instance Name'),
                                \Filament\Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar')
                                    ->iconColor('gray'),
                            ]),
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getColor())
                                    ->icon(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getIcon())
                                    ->formatStateUsing(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getLabel()),
                                \Filament\Infolists\Components\TextEntry::make('status_message')
                                    ->label('Status Message'),
                            ]),
                    ])
                    ->columnSpan(2),

                \Filament\Infolists\Components\Section::make('App & Group')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('storeApp.name')
                            ->label('Store App')
                            ->icon('heroicon-m-squares-2x2')
                            ->iconColor('primary'),
                        \Filament\Infolists\Components\TextEntry::make('userGroup.name')
                            ->label('User Group')
                            ->url(fn ($record) => UserGroupResource::getUrl('view', ['record' => $record->userGroup]))
                            ->openUrlInNewTab()
                            ->icon('heroicon-m-user-group')
                            ->iconColor('success'),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPolydockAppInstances::route('/'),
            'view' => Pages\ViewPolydockAppInstance::route('/{record}'),
            'edit' => Pages\EditPolydockAppInstance::route('/{record}/edit'),
        ];
    }
}
