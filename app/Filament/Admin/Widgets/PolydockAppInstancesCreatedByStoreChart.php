<?php

namespace App\Filament\Admin\Widgets;

use App\Models\PolydockAppInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PolydockAppInstancesCreatedByStoreChart extends WeeklyBarChartWidget
{
    protected static ?string $heading = 'App Instances by Store';

    protected static ?int $sort = 300;

    #[\Override]
    protected function getData(): array
    {
        $bucket = $this->weekBucketSql('polydock_app_instances.created_at');

        $rows = PolydockAppInstance::query()
            ->join('polydock_store_apps', 'polydock_app_instances.polydock_store_app_id', '=', 'polydock_store_apps.id')
            ->join('polydock_stores', 'polydock_store_apps.polydock_store_id', '=', 'polydock_stores.id')
            ->where('polydock_app_instances.created_at', '>=', $this->startDate())
            ->where('polydock_app_instances.created_at', '<=', Carbon::now()->endOfWeek())
            ->select(
                DB::raw("{$bucket} as week"),
                'polydock_stores.name as store_name',
                DB::raw('count(*) as count'),
            )
            ->groupBy(DB::raw($bucket), 'polydock_stores.name')
            ->orderBy('week')
            ->get();

        $series = $this->paletteSeries(
            $rows->pluck('store_name')->unique(),
            ['#0ea5e9', '#f97316', '#84cc16', '#ec4899', '#8b5cf6', '#06b6d4', '#eab308', '#ef4444'],
        );

        return $this->buildWeeklyData($rows, $series, 'store_name');
    }
}
