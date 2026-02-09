<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;

class EditUserGroupProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Group details';
    }

    #[\Override]
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name'),
            ]);
    }
}
