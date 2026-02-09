<?php

namespace App\Filament\Admin\Widgets;

use App\Models\PolydockAppInstance;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PolydockAppInstancesCreatedByTypeChart extends ChartWidget
{
    protected static ?string $heading = 'App Instances by Type';

    protected static ?string $maxHeight = '300px';

    protected static ?int $sort = 400;

    #[\Override]
    protected function getData(): array
    {
        $startDate = Carbon::now()->subWeeks(6)->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();

        // Get instances grouped by week and app type
        $instances = PolydockAppInstance::query()
            ->join('polydock_store_apps', 'polydock_app_instances.polydock_store_app_id', '=', 'polydock_store_apps.id')
            ->where('polydock_app_instances.created_at', '>=', $startDate)
            ->where('polydock_app_instances.created_at', '<=', $endDate)
            ->select(
                DB::raw(
                    'DATE(polydock_app_instances.created_at - INTERVAL WEEKDAY(polydock_app_instances.created_at) DAY) as week',
                ),
                'polydock_store_apps.name as app_type',
                DB::raw('count(*) as count'),
            )
            ->groupBy(
                DB::raw(
                    'DATE(polydock_app_instances.created_at - INTERVAL WEEKDAY(polydock_app_instances.created_at) DAY)',
                ),
                'polydock_store_apps.name',
            )
            ->orderBy('week')
            ->get();

        // Get unique app types
        $appTypes = $instances->pluck('app_type')->unique();

        $weeks = [];
        $typeData = [];

        // Initialize data structure for each app type
        $colors = ['#f59e0b', '#60a5fa', '#34d399', '#f87171', '#a78bfa', '#fbbf24', '#14b8a6', '#f43f5e'];
        foreach ($appTypes as $index => $type) {
            $typeData[$type] = [
                'label' => $type,
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

            // Fill in counts for each app type
            foreach ($appTypes as $type) {
                $count = $weekData->where('app_type', $type)->first()?->count ?? 0;
                $typeData[$type]['data'][] = $count;
            }
        }

        return [
            'datasets' => array_values($typeData),
            'labels' => $weeks,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    #[\Override]
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
