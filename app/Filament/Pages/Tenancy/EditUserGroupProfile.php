<?php

declare(strict_types=1);

namespace App\Filament\Pages\Tenancy;

use Filament\Facades\Filament;
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

    #[\Override]
    public function mount(): void
    {
        parent::mount();

        $tenant = Filament::getTenant();
        if ($tenant !== null) {
            $this->authorize('update', $tenant);
        }
    }
}
