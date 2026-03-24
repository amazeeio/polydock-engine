<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserGroupResource\RelationManagers;

use App\Enums\UserGroupRoleEnum;
use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $inverseRelationship = 'groups';

    #[\Override]
    public function form(Form $form): Form
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
                    ->required()
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255)
                    ->label(fn (string $operation): string => $operation === 'create'
                        ? 'Password'
                        : 'New Password (leave blank to keep current)'),
                Forms\Components\Select::make('role')
                    ->options(UserGroupRoleEnum::class)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->formatStateUsing(fn ($record) => "{$record->first_name} {$record->last_name}")
                    ->url(fn ($record) => UserResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('role')
                    ->badge(),
                Tables\Columns\TextColumn::make('groups_count')
                    ->counts('groups')
                    ->label('Groups'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_user')
                    ->label('Add User')
                    ->modalHeading('Add User to Group')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\ToggleButtons::make('mode')
                            ->label('User Source')
                            ->options([
                                'existing' => 'Existing User',
                                'new' => 'Create New User',
                            ])
                            ->icons([
                                'existing' => 'heroicon-m-user',
                                'new' => 'heroicon-m-user-plus',
                            ])
                            ->default('existing')
                            ->colors([
                                'existing' => 'primary',
                                'new' => 'success',
                            ])
                            ->grouped()
                            ->live(),

                        Forms\Components\Section::make('Search Existing User')
                            ->visible(fn (Get $get) => $get('mode') === 'existing')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Select User')
                                    ->placeholder('Search by email...')
                                    ->options(function (RelationManager $livewire) {
                                        $alreadyInGroup = $livewire->getRelationship()->pluck('users.id')->toArray();

                                        return User::query()
                                            ->whereNotIn('id', $alreadyInGroup)
                                            ->get()
                                            ->pluck('email', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(fn (Get $get) => $get('mode') === 'existing'),
                            ]),

                        Forms\Components\Section::make('Create New User')
                            ->visible(fn (Get $get) => $get('mode') === 'new')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('first_name')
                                            ->label('First Name')
                                            ->required(fn (Get $get) => $get('mode') === 'new')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('last_name')
                                            ->label('Last Name')
                                            ->required(fn (Get $get) => $get('mode') === 'new')
                                            ->maxLength(255),
                                    ]),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email Address')
                                    ->email()
                                    ->required(fn (Get $get) => $get('mode') === 'new')
                                    ->unique('users', 'email')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->required(fn (Get $get) => $get('mode') === 'new')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Select::make('role')
                            ->options(UserGroupRoleEnum::class)
                            ->default(UserGroupRoleEnum::MEMBER)
                            ->required(),
                    ])
                    ->action(function (array $data, RelationManager $livewire) {
                        $userId = $data['user_id'] ?? null;

                        if ($data['mode'] === 'new') {
                            $user = User::create([
                                'first_name' => $data['first_name'],
                                'last_name' => $data['last_name'],
                                'email' => $data['email'],
                                'password' => $data['password'],
                            ]);
                            $userId = $user->id;
                        }

                        if ($userId) {
                            $livewire->getRelationship()->attach($userId, ['role' => $data['role']]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
