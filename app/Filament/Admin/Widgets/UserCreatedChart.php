<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserCreatedChart extends ChartWidget
{
    protected static ?int $sort = 100;
    protected static ?string $heading = 'New Users';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = Carbon::now()->subWeeks(6)->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();
        
        // Get user creation counts grouped by week
        $users = User::query()
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->select(
                DB::raw('DATE(created_at - INTERVAL WEEKDAY(created_at) DAY) as week'),
                DB::raw('count(*) as count')
            )
            ->groupBy(
                DB::raw('DATE(created_at - INTERVAL WEEKDAY(created_at) DAY)')
            )
            ->orderBy('week')
            ->get();

        $weeks = [];
        $counts = [];

        // Fill in data for each week (now 7 weeks including current)
        for ($i = 0; $i <= 6; $i++) {
            $weekStart = $startDate->copy()->addWeeks($i);
            $weekLabel = $weekStart->format('M d');
            $weeks[] = $weekLabel;
            
            $counts[] = $users
                ->where('week', $weekStart->format('Y-m-d'))
                ->first()?->count ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $counts,
                    'backgroundColor' => '#a78bfa', // Purple 400
                    'borderColor' => '#8b5cf6', // Purple 500
                ],
            ],
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
