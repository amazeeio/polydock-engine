<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class HorizonPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Horizon';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $slug = 'horizon';

    protected string $view = 'filament.admin.pages.horizon-page';

    protected static ?string $title = 'Horizon Queue Dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
