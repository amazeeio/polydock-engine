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
