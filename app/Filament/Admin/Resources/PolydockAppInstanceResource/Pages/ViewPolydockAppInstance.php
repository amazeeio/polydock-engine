<?php

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use App\Models\PolydockAppInstance;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Services\LagoonClientService;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ViewPolydockAppInstance extends ViewRecord
{
    protected static string $resource = PolydockAppInstanceResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('rerun_claim_hook')
                ->label('Re-run Claim Hook')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(function ($record): bool {
                    if (blank($record->getKeyValue('lagoon-claim-script'))) {
                        return false;
                    }

                    return in_array($record->status, [
                        PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
                        PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
                        PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
                        PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
                        PolydockAppInstanceStatus::POLYDOCK_CLAIM_FAILED,
                    ], true);
                })
                ->requiresConfirmation()
                ->modalDescription('Queues the configured claim hook again for this instance.')
                ->action(function ($record): void {
                    $skipReadyNotification = data_get($record->data, 'manual_hook_rerun.skip_ready_notification', false)
                        || in_array($record->status, [
                            PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
                            PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
                            PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
                            PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
                        ], true);

                    $data = $record->data ?? [];
                    $data['manual_hook_rerun'] = [
                        'hook' => 'claim',
                        'skip_ready_notification' => $skipReadyNotification,
                    ];

                    $record->data = $data;
                    $record->saveQuietly();

                    $record->setStatus(
                        PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM,
                        'Manual claim hook rerun queued',
                    )->save();

                    activity('audit')
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'action' => 'filament.rerun_claim_hook',
                            'skip_ready_notification' => $skipReadyNotification,
                        ])
                        ->log('Re-ran claim hook (admin UI)');

                    Notification::make()
                        ->title('Claim Hook Queued')
                        ->success()
                        ->body('The claim hook has been queued to run again.')
                        ->send();

                    $this->refreshFormData(['status', 'status_message']);
                }),
            Action::make('trigger_deploy')
                ->label('Trigger Deploy')
                ->icon('heroicon-o-rocket-launch')
                ->color('warning')
                ->requiresConfirmation()
                ->schema([
                    Placeholder::make('environment')
                        ->label('Environment')
                        ->content(fn ($record) => $record->getKeyValue('lagoon-deploy-branch') ?: 'main'),
                    Placeholder::make('last_deployment_date')
                        ->label('Last Polydock Deployment Date')
                        ->content(function ($record) {
                            try {
                                $projectName = $record->getKeyValue('lagoon-project-name');
                                $environmentName = $record->getKeyValue('lagoon-deploy-branch') ?: 'main';

                                if (empty($projectName)) {
                                    return 'Unknown (Missing Project Name)';
                                }

                                $lagoonClientService = app(LagoonClientService::class);
                                $clientConfig = $lagoonClientService->getClientConfig();

                                // Cache the API token (string) to prevent repeated 5-second SSH token fetches
                                $token = Cache::remember('lagoon_api_token_'.md5(json_encode($clientConfig)), now()->addMinutes(2), function () use ($lagoonClientService, $clientConfig) {
                                    return $lagoonClientService->getLagoonToken($clientConfig);
                                });

                                if (empty($token)) {
                                    return 'Error: Unable to authenticate with Lagoon API';
                                }

                                $client = $lagoonClientService->buildClientWithToken($clientConfig, $token);

                                $projectId = $record->getKeyValue('lagoon-project-id');

                                if (! empty($projectId)) {
                                    $deployments = $client->getEnvironmentDeploymentsByProjectId($projectId, $environmentName);
                                } else {
                                    // Older instances without a stored project ID need the project-wide lookup
                                    $deployments = $client->getProjectEnvironmentDeployments($projectName, $environmentName);
                                    $deployments = $deployments['error'] ?? null ? $deployments : ($deployments[$environmentName] ?? []);
                                }

                                if (isset($deployments['error'])) {
                                    return 'API Error: '.(is_array($deployments['error']) ? json_encode($deployments['error']) : $deployments['error']);
                                }

                                if (empty($deployments)) {
                                    return 'No deployments found for environment: '.$environmentName;
                                }

                                $latestComplete = collect($deployments)
                                    ->where('status', 'complete')
                                    ->sortByDesc('created')
                                    ->first();

                                if ($latestComplete && isset($latestComplete['completed'])) {
                                    return Carbon::parse($latestComplete['completed'])->toDateTimeString();
                                }

                                // Fallback to DB log
                                $lastDeployLog = $record->logs()
                                    ->whereJsonContains('data->new_status', PolydockAppInstanceStatus::DEPLOY_COMPLETED->value)
                                    ->latest()
                                    ->first();

                                return $lastDeployLog ? $lastDeployLog->created_at->toDateTimeString().' (Polydock DB)' : 'Never';
                            } catch (Throwable $e) {
                                return 'Error loading: '.$e->getMessage();
                            }
                        }),
                ])
                ->action(function (array $data, $record): void {
                    $projectName = $record->getKeyValue('lagoon-project-name');
                    $environment = $data['environment'] ?? ($record->getKeyValue('lagoon-deploy-branch') ?: 'main');

                    if (empty($projectName)) {
                        Notification::make()
                            ->title('Deployment Failed')
                            ->danger()
                            ->body('Missing Lagoon project name')
                            ->send();

                        return;
                    }

                    if (empty($environment)) {
                        Notification::make()
                            ->title('Deployment Failed')
                            ->danger()
                            ->body('Missing deploy branch')
                            ->send();

                        return;
                    }

                    try {
                        $client = app(LagoonClientService::class)->getAuthenticatedClient();
                        $result = $client->deployProjectEnvironmentByName(
                            projectName: $projectName,
                            deployBranch: $environment,
                        );

                        if (isset($result['error'])) {
                            $errors = is_array($result['error'])
                                ? (json_encode($result['error']) ?: implode(', ', $result['error']))
                                : (string) $result['error'];
                            Notification::make()
                                ->title('Deployment Failed')
                                ->danger()
                                ->body($errors)
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Deployment Triggered')
                                ->success()
                                ->body("Deployment triggered for branch {$environment}")
                                ->send();

                            activity('audit')
                                ->performedOn($record)
                                ->causedBy(auth()->user())
                                ->withProperties([
                                    'action' => 'filament.trigger_deploy',
                                    'project_name' => $projectName,
                                    'environment' => $environment,
                                ])
                                ->log('Triggered Lagoon redeploy (admin UI)');
                        }
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Deployment Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Action::make('retry_failed_instance')
                ->label('Retry Failed Instance')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('danger')
                ->visible(function ($record): bool {
                    return in_array($record->status, [
                        PolydockAppInstanceStatus::POLYDOCK_CLAIM_FAILED,
                        PolydockAppInstanceStatus::DEPLOY_FAILED,
                        PolydockAppInstanceStatus::POST_DEPLOY_FAILED,
                        PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
                        PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
                    ], true);
                })
                ->requiresConfirmation()
                ->modalHeading('Retry Failed Instance')
                ->modalDescription('This will check the Lagoon project/environment state and take corrective action: deploy the environment if missing, trigger a new deployment if it exists, then re-queue the claim process.')
                ->action(function ($record): void {
                    $projectName = $record->getKeyValue('lagoon-project-name');
                    $environment = $record->getKeyValue('lagoon-deploy-branch') ?: 'main';

                    if (empty($projectName)) {
                        Notification::make()
                            ->title('Retry Failed')
                            ->danger()
                            ->body('Missing Lagoon project name on this instance.')
                            ->send();

                        return;
                    }

                    try {
                        $client = app(LagoonClientService::class)->getAuthenticatedClient();

                        // Step 1: Check if the project exists
                        $projectExists = $client->projectExistsByName($projectName);

                        if (! $projectExists) {
                            Notification::make()
                                ->title('Retry Failed')
                                ->danger()
                                ->body("Lagoon project '{$projectName}' does not exist. The instance needs to be re-created from scratch.")
                                ->send();

                            return;
                        }

                        // Step 2: Check if the environment exists
                        $environmentExists = $client->projectEnvironmentExistsByName($projectName, $environment);

                        $action = $environmentExists ? 'Re-deployment' : 'Initial deployment';

                        // Step 4: Queue the claim process
                        $skipReadyNotification = in_array($record->status, [
                            PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
                            PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
                        ], true);

                        $data = $record->data ?? [];
                        $data['manual_hook_rerun'] = [
                            'hook' => 'claim',
                            'skip_ready_notification' => $skipReadyNotification,
                            'retry_context' => [
                                'environment_existed' => $environmentExists,
                                'triggered_at' => now()->toIso8601String(),
                            ],
                        ];

                        $record->data = $data;
                        $record->saveQuietly();

                        $record->setStatus(
                            PolydockAppInstanceStatus::PENDING_DEPLOY,
                            "Retry: {$action} queued for branch {$environment}",
                        )->save();

                        activity('audit')
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'action' => 'filament.retry_failed_instance',
                                'environment_existed' => $environmentExists,
                                'action_taken' => $action,
                            ])
                            ->log('Retried failed instance (admin UI)');

                        Notification::make()
                            ->title('Retry Initiated')
                            ->success()
                            ->body("{$action} queued for '{$environment}'. Instance will progress through the normal deployment flow before claim runs.")
                            ->send();

                        $this->refreshFormData(['status', 'status_message']);
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Retry Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Action::make('extend_trial')
                ->label('Extend Trial')
                ->icon('heroicon-o-calendar')
                ->schema([
                    DatePicker::make('new_trial_end_date')
                        ->label('New Trial End Date')
                        ->required()
                        ->minDate(now())
                        ->default(fn ($record) => $record->trial_ends_at),
                ])
                ->action(function (array $data, $record): void {
                    $newEndDate = Carbon::parse($data['new_trial_end_date']);

                    try {
                        $record->calculateAndSetTrialDatesFromEndDate($newEndDate, true);

                        activity('audit')
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'action' => 'filament.extend_trial',
                                'new_end_date' => $newEndDate->toDateTimeString(),
                            ])
                            ->log('Extended trial (admin UI)');

                        Notification::make()
                            ->title('Trial Extended')
                            ->success()
                            ->body("Trial end date updated to {$newEndDate->toFormattedDateString()}")
                            ->send();

                        $this->refreshFormData(['trial_ends_at']);
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Update Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Action::make('delete_instance')
                ->label('Delete Instance')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(function ($record): bool {
                    // Hide once the instance is already being torn down or gone.
                    $alreadyTearingDown = array_merge(
                        PolydockAppInstance::$stageRemoveStatuses,
                        PolydockAppInstance::$stagePurgeStatuses,
                    );

                    return ! in_array($record->status, $alreadyTearingDown, true);
                })
                ->requiresConfirmation()
                ->modalHeading('Delete this app instance?')
                ->modalDescription('This will start the standard removal pipeline: the Lagoon environment will be deleted first, then the Lagoon project will be fully deleted after the grace period (or immediately if you tick "Skip grace period").')
                ->modalSubmitActionLabel('Delete')
                ->schema([
                    Toggle::make('skip_grace_period')
                        ->label('Skip grace period and force-purge the Lagoon project as soon as the environment is gone')
                        ->helperText('Equivalent to clicking "Force Full Delete" the moment the instance reaches REMOVED.')
                        ->default(false),
                ])
                ->action(function (array $data, $record): void {
                    $skipGrace = (bool) ($data['skip_grace_period'] ?? false);

                    if ($skipGrace) {
                        $record->force_purge_requested_at = now();
                    }

                    $record->setStatus(
                        PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
                        $skipGrace
                            ? 'Deletion requested via admin UI (force-purge)'
                            : 'Deletion requested via admin UI',
                    );
                    $record->save();

                    activity('audit')
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'action' => 'filament.delete_instance',
                            'skip_grace_period' => $skipGrace,
                        ])
                        ->log('Initiated instance delete (admin UI)');

                    Notification::make()
                        ->title('Deletion Queued')
                        ->success()
                        ->body($skipGrace
                            ? 'Removal pipeline started. The Lagoon project will be fully purged as soon as the environment is gone.'
                            : 'Removal pipeline started. The Lagoon project will be fully purged after the grace period.')
                        ->send();

                    $this->refreshFormData(['status', 'status_message']);
                }),
            Action::make('force_full_delete')
                ->label('Force Full Delete (Lagoon)')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn ($record): bool => $record->status === PolydockAppInstanceStatus::REMOVED && ! $record->trashed())
                ->requiresConfirmation()
                ->modalHeading('Force full Lagoon project deletion?')
                ->modalDescription('This skips the grace period and immediately tries to delete the Lagoon project. If environments are still being torn down, this will keep retrying until they are gone or the polling cap is reached.')
                ->action(function ($record): void {
                    $now = now();
                    $record->force_purge_requested_at = $now;
                    $record->purge_eligible_at = $now;
                    $record->purge_attempts = 0;
                    $record->purge_failure_reason = null;
                    $record->purge_last_attempted_at = null;
                    $record->setStatus(PolydockAppInstanceStatus::PENDING_PURGE, 'Force purge requested via admin UI');
                    $record->save();

                    activity('audit')
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'action' => 'filament.force_full_delete',
                        ])
                        ->log('Force purge triggered (admin UI)');

                    Notification::make()
                        ->title('Force Purge Queued')
                        ->success()
                        ->body('A full Lagoon project deletion has been queued for this instance.')
                        ->send();

                    $this->refreshFormData(['status', 'status_message']);
                }),
            Action::make('retry_purge')
                ->label('Retry Purge')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn ($record): bool => $record->status === PolydockAppInstanceStatus::PURGE_FAILED && ! $record->trashed())
                ->requiresConfirmation()
                ->modalDescription('Resets purge attempts and returns the instance to REMOVED with a fresh grace period before purge dispatch.')
                ->action(function ($record): void {
                    $graceDays = (int) config('polydock.cleanup.purge_grace_days', 14);
                    $record->purge_attempts = 0;
                    $record->purge_failure_reason = null;
                    $record->purge_last_attempted_at = null;
                    $record->force_purge_requested_at = null;
                    $record->purge_eligible_at = now()->addDays($graceDays);
                    $record->setStatus(PolydockAppInstanceStatus::REMOVED, 'Purge retry requested via admin UI; grace period restarted');
                    $record->save();

                    activity('audit')
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'action' => 'filament.retry_purge',
                            'grace_days' => $graceDays,
                        ])
                        ->log('Purge retry requested (admin UI)');

                    Notification::make()
                        ->title('Purge Retry Scheduled')
                        ->success()
                        ->body("The purge counters were reset and the grace period was restarted ({$graceDays} day(s)).")
                        ->send();

                    $this->refreshFormData(['status', 'status_message', 'purge_eligible_at', 'force_purge_requested_at']);
                }),
            Action::make('cancel_force_purge')
                ->label('Cancel Force Delete')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn ($record): bool => $record->force_purge_requested_at !== null
                    && $record->status === PolydockAppInstanceStatus::REMOVED
                    && ! $record->trashed())
                ->requiresConfirmation()
                ->action(function ($record): void {
                    $record->force_purge_requested_at = null;
                    $record->save();

                    activity('audit')
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'action' => 'filament.cancel_force_purge',
                        ])
                        ->log('Force purge cancelled (admin UI)');

                    Notification::make()
                        ->title('Force Purge Cancelled')
                        ->success()
                        ->body('The instance will fall back to the standard grace period.')
                        ->send();
                }),
        ];
    }
}
