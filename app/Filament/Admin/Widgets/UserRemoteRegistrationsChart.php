<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Models\UserRemoteRegistration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserRemoteRegistrationsChart extends WeeklyBarChartWidget
{
    protected static ?string $heading = 'Remote Registrations by Status';

    protected static ?int $sort = 200;

    #[\Override]
    protected function getData(): array
    {
        $bucket = $this->weekBucketSql();

        $rows = UserRemoteRegistration::query()
            ->where('created_at', '>=', $this->startDate())
            ->where('created_at', '<=', Carbon::now()->endOfWeek())
            ->select(
                DB::raw("{$bucket} as week"),
                'status',
                DB::raw('count(*) as count'),
            )
            ->groupBy(DB::raw($bucket), 'status')
            ->orderBy('week')
            ->get();

        $series = [];
        foreach (UserRemoteRegistrationStatusEnum::cases() as $status) {
            $series[$status->value] = [
                'label' => $status->getLabel(),
                'backgroundColor' => match ($status->value) {
                    'pending' => '#fbbf24', // Amber
                    'processing' => '#60a5fa', // Blue
                    'success' => '#34d399', // Green
                    'failed' => '#f87171', // Red
                    default => '#9ca3af', // Gray
                },
            ];
        }

        return $this->buildWeeklyData($rows, $series, 'status');
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
