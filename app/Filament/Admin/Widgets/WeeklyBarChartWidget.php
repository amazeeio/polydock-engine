<?php

namespace App\Filament\Admin\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

/**
 * Shared shape of the dashboard bar charts: a 7-bucket weekly window
 * (6 past weeks + current), MySQL week bucketing, and a per-series fill
 * loop. Subclasses supply the query rows and series definitions.
 */
abstract class WeeklyBarChartWidget extends ChartWidget
{
    protected static ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    /** Default options for the stacked multi-series charts; override as needed. */
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

    protected function startDate(): Carbon
    {
        return Carbon::now()->subWeeks(6)->startOfWeek();
    }

    /** SQL expression bucketing a datetime column to the Monday of its week. */
    protected function weekBucketSql(string $column = 'created_at'): string
    {
        return "DATE({$column} - INTERVAL WEEKDAY({$column}) DAY)";
    }

    /**
     * Series metadata for dynamic series names, colored from a palette.
     *
     * @return array<string, array{label: string, backgroundColor: string}>
     */
    protected function paletteSeries(Collection $names, array $colors): array
    {
        return $names->values()
            ->mapWithKeys(fn ($name, int $index) => [$name => [
                'label' => $name,
                'backgroundColor' => $colors[$index % count($colors)],
            ]])
            ->all();
    }

    /**
     * Build the chart data from rows carrying `week` + `count` columns.
     *
     * @param  Collection  $rows  Aggregated rows with a `week` (Y-m-d) and `count`
     * @param  array<string|int, array>  $seriesMeta  series key => dataset metadata (label, colors, ...)
     * @param  string|null  $seriesField  Row column holding the series key; null for a single series
     */
    protected function buildWeeklyData(Collection $rows, array $seriesMeta, ?string $seriesField): array
    {
        $startDate = $this->startDate();
        $weeks = [];
        $datasets = [];

        foreach ($seriesMeta as $key => $meta) {
            $datasets[$key] = $meta + ['data' => []];
        }

        // Fill in data for each week (7 buckets including current)
        for ($i = 0; $i <= 6; $i++) {
            $weekStart = $startDate->copy()->addWeeks($i);
            $weeks[] = $weekStart->format('M d');

            $weekData = $rows->where('week', $weekStart->format('Y-m-d'));

            foreach (array_keys($seriesMeta) as $key) {
                $match = $seriesField === null ? $weekData : $weekData->where($seriesField, $key);
                $datasets[$key]['data'][] = $match->first()->count ?? 0;
            }
        }

        return [
            'datasets' => array_values($datasets),
            'labels' => $weeks,
        ];
    }
}
