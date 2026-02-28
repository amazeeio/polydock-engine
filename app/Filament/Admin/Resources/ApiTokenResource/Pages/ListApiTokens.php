<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ApiTokenResource\Pages;

use App\Filament\Admin\Resources\ApiTokenResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListApiTokens extends ListRecords
{
    protected static string $resource = ApiTokenResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createToken')
                ->label('Create Token')
                ->icon('heroicon-o-plus')
                ->modalHeading('Create API token')
                ->modalDescription('The token will only be shown once after creation.')
                ->form([
                    Forms\Components\Select::make('user_id')
                        ->label('Owner user')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(User::query()->orderBy('email')->pluck('email', 'id')),
                    Forms\Components\TextInput::make('token_name')
                        ->label('Token name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\CheckboxList::make('abilities')
                        ->label('Abilities')
                        ->options([
                            'instances.read' => 'instances.read',
                            'instances.write' => 'instances.write',
                            '*' => '* (full access)',
                        ])
                        ->default(['instances.read'])
                        ->required()
                        ->minItems(1)
                        ->columns(1),
                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expires at')
                        ->seconds(false),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = User::query()->findOrFail($data['user_id']);
                    $expiresAt = ! empty($data['expires_at']) ? Carbon::parse((string) $data['expires_at']) : null;
                    $token = $user->createToken(
                        name: (string) $data['token_name'],
                        abilities: array_values($data['abilities']),
                        expiresAt: $expiresAt,
                    );

                    Notification::make()
                        ->title('API token created')
                        ->body("Copy this token now. It will not be shown again:\n{$token->plainTextToken}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
