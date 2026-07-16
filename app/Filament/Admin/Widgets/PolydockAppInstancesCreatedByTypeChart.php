<?php

namespace App\Filament\Admin\Widgets;

use App\Models\PolydockAppInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PolydockAppInstancesCreatedByTypeChart extends WeeklyBarChartWidget
{
    protected static ?string $heading = 'App Instances by Type';

    protected static ?int $sort = 400;

    #[\Override]
    protected function getData(): array
    {
        $bucket = $this->weekBucketSql('polydock_app_instances.created_at');

        $rows = PolydockAppInstance::query()
            ->join('polydock_store_apps', 'polydock_app_instances.polydock_store_app_id', '=', 'polydock_store_apps.id')
            ->where('polydock_app_instances.created_at', '>=', $this->startDate())
            ->where('polydock_app_instances.created_at', '<=', Carbon::now()->endOfWeek())
            ->select(
                DB::raw("{$bucket} as week"),
                'polydock_store_apps.name as app_type',
                DB::raw('count(*) as count'),
            )
            ->groupBy(DB::raw($bucket), 'polydock_store_apps.name')
            ->orderBy('week')
            ->get();

        $series = $this->paletteSeries(
            $rows->pluck('app_type')->unique(),
            ['#f59e0b', '#60a5fa', '#34d399', '#f87171', '#a78bfa', '#fbbf24', '#14b8a6', '#f43f5e'],
        );

        return $this->buildWeeklyData($rows, $series, 'app_type');
    }
}
