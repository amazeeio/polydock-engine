<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;

class HorizonPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Horizon';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $slug = 'horizon';

    protected static string $view = 'filament.admin.pages.horizon-page';

    protected static ?string $title = 'Horizon Queue Dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
