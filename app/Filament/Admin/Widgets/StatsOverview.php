<?php

namespace App\Filament\Admin\Widgets;

use App\Models\PolydockAppInstance;
use App\Models\User;
use App\Models\UserRemoteRegistration;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    #[\Override]
    protected function getStats(): array
    {
        // Full-table counts, cached briefly: the dashboard tolerates a minute
        // of staleness better than three COUNT(*) scans per render.
        $totals = Cache::remember('admin-stats-overview', 60, fn (): array => [
            'users' => User::count(),
            'registrations' => UserRemoteRegistration::count(),
            'instances' => PolydockAppInstance::count(),
        ]);

        return [
            Stat::make('Total Users', $totals['users'])
                ->description('Total registered users')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Total Remote Registrations', $totals['registrations'])
                ->description('Total registration attempts')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            Stat::make('Total App Instances', $totals['instances'])
                ->description('Total app instances created')
                ->descriptionIcon('heroicon-m-server')
                ->color('success'),
        ];
    }
}
