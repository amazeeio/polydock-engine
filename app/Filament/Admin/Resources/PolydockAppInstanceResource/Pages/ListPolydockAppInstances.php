<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Services\ClaimExistingProjectService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
            Action::make('claim_existing_project')
                ->label('Claim existing Lagoon project')
                ->icon('heroicon-o-link')
                // Claiming creates an instance, so gate it like CreateAction.
                ->visible(fn (): bool => PolydockAppInstanceResource::canCreate())
                ->modalHeading('Claim an existing Lagoon project')
                ->modalDescription('Adopt a project that already exists on Lagoon so Polydock auto-updates it with scheduled deployments. Polydock grants its deploy group access to the project and will never delete a project it did not create.')
                ->modalSubmitActionLabel('Claim')
                ->form([
                    TextInput::make('project_name')
                        ->label('Lagoon project name')
                        ->required()
                        ->helperText('Must exactly match the project name on Lagoon.'),
                    Select::make('polydock_store_app_id')
                        ->label('Store app')
                        ->helperText('Determines the app type, region, deploy group and redeploy schedule. Only store apps with scheduled redeploys enabled are listed — that schedule is the point of adopting.')
                        ->required()
                        ->searchable()
                        ->options(fn () => PolydockStoreApp::query()
                            ->where('redeploy_enabled', true)
                            ->whereNotNull('redeploy_interval_days')
                            ->orderBy('name')
                            ->pluck('name', 'id')),
                    Select::make('user_group_id')
                        ->label('Owner user group')
                        ->required()
                        ->searchable()
                        ->options(fn () => UserGroup::query()->orderBy('name')->pluck('name', 'id')),
                ])
                ->action(function (array $data): void {
                    try {
                        $instance = app(ClaimExistingProjectService::class)->claim(
                            PolydockStoreApp::findOrFail($data['polydock_store_app_id']),
                            UserGroup::findOrFail($data['user_group_id']),
                            $data['project_name'],
                        );

                        activity('audit')
                            ->performedOn($instance)
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'action' => 'filament.claim_existing_project',
                                'project_name' => $data['project_name'],
                            ])
                            ->log('Claimed existing Lagoon project (admin UI)');

                        Notification::make()
                            ->title('Project claimed')
                            ->success()
                            ->body("Instance #{$instance->id} now tracks '{$data['project_name']}' and is eligible for scheduled redeploys.")
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Claim failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
        ];
    }

    public function getTabs(): array
    {
        $inProgressStatuses = [
            PolydockAppInstanceStatus::NEW,
            ...PolydockAppInstance::$stageCreateStatuses,
            ...PolydockAppInstance::$stageDeployStatuses,
            ...PolydockAppInstance::$stageClaimStatuses,
            ...PolydockAppInstance::$stageUpgradeStatuses,
            ...array_filter(PolydockAppInstance::$stageRemoveStatuses, fn ($status) => $status !== PolydockAppInstanceStatus::REMOVED),
        ];

        // Each tab's scope is written once; all badges derive from a single
        // grouped count query instead of one COUNT per tab per render.
        $scopes = [
            'active' => ['Active', fn (Builder $query) => $query->where('status', '!=', PolydockAppInstanceStatus::REMOVED)],
            'in_progress' => ['In Progress', fn (Builder $query) => $query->whereIn('status', $inProgressStatuses)],
            'healthy_claimed' => ['Healthy (Claimed)', fn (Builder $query) => $query->where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED)],
            'healthy_unclaimed' => ['Healthy (Unclaimed)', fn (Builder $query) => $query->where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED)],
            'removed' => ['Removed', fn (Builder $query) => $query->where('status', PolydockAppInstanceStatus::REMOVED)],
        ];

        $countsByStatus = static::$resource::getEloquentQuery()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = $countsByStatus->sum();
        $removed = (int) $countsByStatus->get(PolydockAppInstanceStatus::REMOVED->value, 0);

        $badges = [
            'active' => $total - $removed,
            // unique() restores the set semantics whereIn had: NEW appears
            // both explicitly and inside $stageCreateStatuses, and a status
            // listed twice must not be counted twice.
            'in_progress' => collect($inProgressStatuses)
                ->unique(fn (PolydockAppInstanceStatus $status) => $status->value)
                ->sum(fn (PolydockAppInstanceStatus $status) => (int) $countsByStatus->get($status->value, 0)),
            'healthy_claimed' => (int) $countsByStatus->get(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED->value, 0),
            'healthy_unclaimed' => (int) $countsByStatus->get(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED->value, 0),
            'removed' => $removed,
        ];

        $tabs = [];
        foreach ($scopes as $key => [$label, $scope]) {
            $tabs[$key] = Tab::make($label)
                ->modifyQueryUsing($scope)
                ->badge($badges[$key]);
        }

        $tabs['all'] = Tab::make('All')
            ->badge($total);

        return $tabs;
    }
}
