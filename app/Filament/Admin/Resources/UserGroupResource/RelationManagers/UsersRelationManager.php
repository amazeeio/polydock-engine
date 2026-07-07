<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserGroupResource\RelationManagers;

use App\Enums\UserGroupRoleEnum;
use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\GroupMembershipService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $inverseRelationship = 'groups';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->required()
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255)
                    ->label(fn (string $operation): string => $operation === 'create'
                        ? 'Password'
                        : 'New Password (leave blank to keep current)'),
                Select::make('role')
                    ->options(UserGroupRoleEnum::class)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->formatStateUsing(fn ($record) => "{$record->first_name} {$record->last_name}")
                    ->url(fn ($record) => UserResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                TextColumn::make('email'),
                TextColumn::make('pivot.role')
                    ->badge(),
                TextColumn::make('groups_count')
                    ->counts('groups')
                    ->label('Groups'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('add_user')
                    ->label('Add User')
                    ->modalHeading('Add User to Group')
                    ->modalWidth('lg')
                    ->schema([
                        ToggleButtons::make('mode')
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

                        Section::make('Search Existing User')
                            ->visible(fn (Get $get) => $get('mode') === 'existing')
                            ->schema([
                                Select::make('user_id')
                                    ->label('Select User')
                                    ->placeholder('Search by email...')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search): array {
                                        $alreadyInGroup = $this->getRelationship()->pluck('users.id')->toArray();

                                        return User::query()
                                            ->whereNotIn('id', $alreadyInGroup)
                                            ->where('email', 'like', '%'.$search.'%')
                                            ->limit(50)
                                            ->pluck('email', 'id')
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        if ($value === null) {
                                            return null;
                                        }

                                        return User::query()
                                            ->whereKey($value)
                                            ->value('email');
                                    })
                                    ->required(fn (Get $get) => $get('mode') === 'existing'),
                            ]),

                        Section::make('Create New User')
                            ->visible(fn (Get $get) => $get('mode') === 'new')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('first_name')
                                            ->label('First Name')
                                            ->required(fn (Get $get) => $get('mode') === 'new')
                                            ->maxLength(255),
                                        TextInput::make('last_name')
                                            ->label('Last Name')
                                            ->required(fn (Get $get) => $get('mode') === 'new')
                                            ->maxLength(255),
                                    ]),
                                TextInput::make('email')
                                    ->label('Email Address')
                                    ->email()
                                    ->required(fn (Get $get) => $get('mode') === 'new')
                                    ->unique('users', 'email')
                                    ->maxLength(255),
                                TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->required(fn (Get $get) => $get('mode') === 'new')
                                    ->maxLength(255),
                            ]),

                        Select::make('role')
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
                            $user = $user ?? User::findOrFail($userId);
                            /** @var UserGroup $group */
                            $group = $livewire->getOwnerRecord();
                            $role = $data['role'] instanceof UserGroupRoleEnum
                                ? $data['role']
                                : UserGroupRoleEnum::from($data['role']);
                            app(GroupMembershipService::class)->addUserToGroup($user, $group, $role);
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->before(function ($record, RelationManager $livewire, array $data): void {
                        /** @var UserGroup $group */
                        $group = $livewire->getOwnerRecord();
                        $previousRole = $record->pivot->getAttribute('role');
                        $newRole = $data['role'] instanceof UserGroupRoleEnum
                            ? $data['role']->value
                            : $data['role'];

                        if ($previousRole === $newRole) {
                            return;
                        }

                        activity('audit')
                            ->performedOn($group)
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'action' => 'group.member_role_changed',
                                'user_id' => $record->id,
                                'user_email' => $record->email,
                                'previous_role' => $previousRole,
                                'new_role' => $newRole,
                            ])
                            ->log("User '{$record->email}' role changed from '{$previousRole}' to '{$newRole}'");
                    }),
                DetachAction::make()
                    ->before(function ($record, RelationManager $livewire): void {
                        /** @var UserGroup $group */
                        $group = $livewire->getOwnerRecord();
                        $previousRole = $record->pivot->getAttribute('role');

                        activity('audit')
                            ->performedOn($group)
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'action' => 'group.member_removed',
                                'user_id' => $record->id,
                                'user_email' => $record->email,
                                'previous_role' => $previousRole,
                            ])
                            ->log("User '{$record->email}' removed from group");
                    }),
                DeleteAction::make()
                    ->before(function ($record, RelationManager $livewire): void {
                        /** @var UserGroup $group */
                        $group = $livewire->getOwnerRecord();

                        activity('audit')
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'action' => 'user.account_deleted',
                                'user_id' => $record->id,
                                'user_email' => $record->email,
                                'group_id' => $group->id,
                                'group_name' => $group->name,
                            ])
                            ->log("User account '{$record->email}' deleted");
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
