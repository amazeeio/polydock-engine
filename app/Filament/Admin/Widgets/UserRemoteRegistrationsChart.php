<?php

namespace App\Filament\Admin\Widgets;

use App\Models\UserRemoteRegistration;
use App\Enums\UserRemoteRegistrationStatusEnum;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserRemoteRegistrationsChart extends ChartWidget
{
    protected static ?string $heading = 'Remote Registrations by Status';
    protected static ?string $maxHeight = '300px';
    protected static ?int $sort = 200;

    protected function getData(): array
    {
        $startDate = Carbon::now()->subWeeks(6)->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();
        
        // Get registrations grouped by week and status
        $registrations = UserRemoteRegistration::query()
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->select(
                DB::raw('DATE(created_at - INTERVAL WEEKDAY(created_at) DAY) as week'),
                'status',
                DB::raw('count(*) as count')
            )
            ->groupBy(
                DB::raw('DATE(created_at - INTERVAL WEEKDAY(created_at) DAY)'),
                'status'
            )
            ->orderBy('week')
            ->get();

        $weeks = [];
        $statusData = [];
        
        // Initialize data structure for each status
        foreach (UserRemoteRegistrationStatusEnum::cases() as $status) {
            $statusData[$status->value] = [
                'label' => $status->getLabel(),
                'data' => [],
                'backgroundColor' => match($status->value) {
                    'pending' => '#fbbf24', // Amber
                    'processing' => '#60a5fa', // Blue
                    'success' => '#34d399', // Green
                    'failed' => '#f87171', // Red
                    default => '#9ca3af', // Gray
                },
            ];
        }

        // Fill in data for each week (now 7 weeks including current)
        for ($i = 0; $i <= 6; $i++) {
            $weekStart = $startDate->copy()->addWeeks($i);
            $weekLabel = $weekStart->format('M d');
            $weeks[] = $weekLabel;
            
            $weekData = $registrations->where('week', $weekStart->format('Y-m-d'));
            
            // Fill in counts for each status
            foreach (UserRemoteRegistrationStatusEnum::cases() as $status) {
                $count = $weekData->where('status', $status->value)->first()?->count ?? 0;
                $statusData[$status->value]['data'][] = $count;
            }
        }

        return [
            'datasets' => array_values($statusData),
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
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
