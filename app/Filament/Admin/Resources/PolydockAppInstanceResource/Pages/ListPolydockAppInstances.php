<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use App\Models\PolydockAppInstance;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPolydockAppInstances extends ListRecords
{
    protected static string $resource = PolydockAppInstanceResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', '!=', PolydockAppInstanceStatus::REMOVED))
                ->badge(static::$resource::getEloquentQuery()->where('status', '!=', PolydockAppInstanceStatus::REMOVED)->count()),

            'in_progress' => Tab::make('In Progress')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    PolydockAppInstanceStatus::NEW,
                    ...PolydockAppInstance::$stageCreateStatuses,
                    ...PolydockAppInstance::$stageDeployStatuses,
                    ...PolydockAppInstance::$stageClaimStatuses,
                    ...PolydockAppInstance::$stageUpgradeStatuses,
                    ...array_filter(PolydockAppInstance::$stageRemoveStatuses, fn ($status) => $status !== PolydockAppInstanceStatus::REMOVED),
                ]))
                ->badge(static::$resource::getEloquentQuery()->whereIn('status', [
                    PolydockAppInstanceStatus::NEW,
                    ...PolydockAppInstance::$stageCreateStatuses,
                    ...PolydockAppInstance::$stageDeployStatuses,
                    ...PolydockAppInstance::$stageClaimStatuses,
                    ...PolydockAppInstance::$stageUpgradeStatuses,
                    ...array_filter(PolydockAppInstance::$stageRemoveStatuses, fn ($status) => $status !== PolydockAppInstanceStatus::REMOVED),
                ])->count()),

            'healthy_claimed' => Tab::make('Healthy (Claimed)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED))
                ->badge(static::$resource::getEloquentQuery()->where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED)->count()),

            'healthy_unclaimed' => Tab::make('Healthy (Unclaimed)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED))
                ->badge(static::$resource::getEloquentQuery()->where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED)->count()),

            'removed' => Tab::make('Removed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PolydockAppInstanceStatus::REMOVED))
                ->badge(static::$resource::getEloquentQuery()->where('status', PolydockAppInstanceStatus::REMOVED)->count()),

            'all' => Tab::make('All')
                ->badge(static::$resource::getEloquentQuery()->count()),
        ];
    }
}
