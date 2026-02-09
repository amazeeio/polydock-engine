<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\UserGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;

class RegisterUserGroup extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Create new group';
    }

    #[\Override]
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->unique('user_groups', 'name')
                    ->required(),
            ]);
    }

    #[\Override]
    protected function handleRegistration(array $data): UserGroup
    {
        $userGroup = UserGroup::create($data);

        $userGroup->members()->attach(auth()->user());

        return $userGroup;
    }
}
