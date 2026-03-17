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
use FreedomtechHosting\FtLagoonPhp\Ssh;
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
                    // TODO ensure we get the correct instance_id ?
                    $environment = $data['environment'] ?? ($record->getKeyValue('lagoon-deploy-branch') ?: 'main');

                    if (empty($projectName)) {
                        Notification::make()
                            ->title('Deployment Failed')
                            ->danger()
                            ->body('Missing Lagoon project name')
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
        ];
    }
}
