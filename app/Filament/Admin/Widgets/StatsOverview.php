<?php

namespace App\Filament\Admin\Widgets;

use App\Models\PolydockAppInstance;
use App\Models\User;
use App\Models\UserRemoteRegistration;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    #[\Override]
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('Total registered users')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Total Remote Registrations', UserRemoteRegistration::count())
                ->description('Total registration attempts')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            Stat::make('Total App Instances', PolydockAppInstance::count())
                ->description('Total app instances created')
                ->descriptionIcon('heroicon-m-server')
                ->color('success'),
        ];
    }
}
