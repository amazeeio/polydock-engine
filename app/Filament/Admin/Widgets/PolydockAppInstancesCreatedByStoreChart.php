<?php

namespace App\Filament\Admin\Widgets;

use App\Models\PolydockAppInstance;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PolydockAppInstancesCreatedByStoreChart extends ChartWidget
{
    protected static ?string $heading = 'App Instances by Store';

    protected static ?string $maxHeight = '300px';

    protected static ?int $sort = 300;

    protected function getData(): array
    {
        $startDate = Carbon::now()->subWeeks(6)->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();

        // Get instances grouped by week and store
        $instances = PolydockAppInstance::query()
            ->join('polydock_store_apps', 'polydock_app_instances.polydock_store_app_id', '=', 'polydock_store_apps.id')
            ->join('polydock_stores', 'polydock_store_apps.polydock_store_id', '=', 'polydock_stores.id')
            ->where('polydock_app_instances.created_at', '>=', $startDate)
            ->where('polydock_app_instances.created_at', '<=', $endDate)
            ->select(
                DB::raw('DATE(polydock_app_instances.created_at - INTERVAL WEEKDAY(polydock_app_instances.created_at) DAY) as week'),
                'polydock_stores.name as store_name',
                DB::raw('count(*) as count')
            )
            ->groupBy(
                DB::raw('DATE(polydock_app_instances.created_at - INTERVAL WEEKDAY(polydock_app_instances.created_at) DAY)'),
                'polydock_stores.name'
            )
            ->orderBy('week')
            ->get();

        // Get unique store names
        $storeNames = $instances->pluck('store_name')->unique();

        $weeks = [];
        $storeData = [];

        // Initialize data structure for each store
        $colors = ['#0ea5e9', '#f97316', '#84cc16', '#ec4899', '#8b5cf6', '#06b6d4', '#eab308', '#ef4444'];
        foreach ($storeNames as $index => $name) {
            $storeData[$name] = [
                'label' => $name,
                'data' => [],
                'backgroundColor' => $colors[$index % count($colors)],
            ];
        }

        // Fill in data for each week (now 7 weeks including current)
        for ($i = 0; $i <= 6; $i++) {
            $weekStart = $startDate->copy()->addWeeks($i);
            $weekLabel = $weekStart->format('M d');
            $weeks[] = $weekLabel;

            $weekData = $instances->where('week', $weekStart->format('Y-m-d'));

            // Fill in counts for each store
            foreach ($storeNames as $name) {
                $count = $weekData->where('store_name', $name)->first()?->count ?? 0;
                $storeData[$name]['data'][] = $count;
            }
        }

        return [
            'datasets' => array_values($storeData),
            'labels' => $weeks,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1, // Force whole numbers
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
        ];
    }
}
