<?php

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use App\Services\LagoonClientService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Support\Facades\Cache;

class ViewPolydockAppInstance extends ViewRecord
{
    protected static string $resource = PolydockAppInstanceResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
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
                ->form([
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

                                $deployments = $client->getProjectEnvironmentDeployments($projectName, $environmentName);

                                if (isset($deployments['error'])) {
                                    return 'API Error: '.(is_array($deployments['error']) ? json_encode($deployments['error']) : $deployments['error']);
                                }

                                if (empty($deployments) || empty($deployments[$environmentName])) {
                                    return 'No deployments found for environment: '.$environmentName;
                                }

                                $latestComplete = collect($deployments[$environmentName])
                                    ->where('status', 'complete')
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
                            } catch (\Throwable $e) {
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
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Deployment Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Action::make('extend_trial')
                ->label('Extend Trial')
                ->icon('heroicon-o-calendar')
                ->form([
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

                        Notification::make()
                            ->title('Trial Extended')
                            ->success()
                            ->body("Trial end date updated to {$newEndDate->toFormattedDateString()}")
                            ->send();

                        $this->refreshFormData(['trial_ends_at']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Update Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Action::make('force_full_delete')
                ->label('Force Full Delete (Lagoon)')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn ($record): bool => $record->status === PolydockAppInstanceStatus::REMOVED)
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
                ->visible(fn ($record): bool => $record->status === PolydockAppInstanceStatus::PURGE_FAILED)
                ->requiresConfirmation()
                ->modalDescription('Resets the purge attempt counter and re-queues a Lagoon project deletion attempt.')
                ->action(function ($record): void {
                    $record->purge_attempts = 0;
                    $record->purge_failure_reason = null;
                    $record->purge_last_attempted_at = null;
                    $record->setStatus(PolydockAppInstanceStatus::PENDING_PURGE, 'Purge retry requested via admin UI');
                    $record->save();

                    Notification::make()
                        ->title('Purge Re-queued')
                        ->success()
                        ->body('The purge attempt counter has been reset and the job has been re-queued.')
                        ->send();

                    $this->refreshFormData(['status', 'status_message']);
                }),
            Action::make('cancel_force_purge')
                ->label('Cancel Force Delete')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn ($record): bool => $record->force_purge_requested_at !== null
                    && $record->status === PolydockAppInstanceStatus::REMOVED)
                ->requiresConfirmation()
                ->action(function ($record): void {
                    $record->force_purge_requested_at = null;
                    $record->save();

                    Notification::make()
                        ->title('Force Purge Cancelled')
                        ->success()
                        ->body('The instance will fall back to the standard grace period.')
                        ->send();
                }),
        ];
    }
}
