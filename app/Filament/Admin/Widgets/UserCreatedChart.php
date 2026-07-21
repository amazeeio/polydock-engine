<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserCreatedChart extends WeeklyBarChartWidget
{
    protected static ?int $sort = 100;

    protected static ?string $heading = 'New Users';

    #[\Override]
    protected function getData(): array
    {
        $bucket = $this->weekBucketSql();

        $rows = User::query()
            ->where('created_at', '>=', $this->startDate())
            ->where('created_at', '<=', Carbon::now()->endOfWeek())
            ->select(
                DB::raw("{$bucket} as week"),
                DB::raw('count(*) as count'),
            )
            ->groupBy(DB::raw($bucket))
            ->orderBy('week')
            ->get();

        return $this->buildWeeklyData($rows, [
            'users' => [
                'label' => 'New Users',
                'backgroundColor' => '#a78bfa', // Purple 400
                'borderColor' => '#8b5cf6', // Purple 500
            ],
        ], null);
    }

    #[\Override]
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false, // Hide legend since we only have one dataset
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1, // Force whole numbers
                    ],
                ],
            ],
        ];
    }
}
