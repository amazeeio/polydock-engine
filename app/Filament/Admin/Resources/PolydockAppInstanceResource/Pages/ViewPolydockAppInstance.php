<?php

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use FreedomtechHosting\FtLagoonPhp\Client;
use FreedomtechHosting\FtLagoonPhp\Ssh;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Support\Facades\Artisan;
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

                                $sshConfig = config('polydock.service_providers_singletons.PolydockServiceProviderFTLagoon', []);
                                $clientConfig = [
                                    'ssh_user' => $sshConfig['ssh_user'] ?? 'lagoon',
                                    'ssh_server' => $sshConfig['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud',
                                    'ssh_port' => $sshConfig['ssh_port'] ?? '32222',
                                    'endpoint' => $sshConfig['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql',
                                    'ssh_private_key_file' => $sshConfig['ssh_private_key_file'] ?? getenv('HOME').'/.ssh/id_rsa',
                                ];

                                // Cache the SSH token to prevent 5-second page loads
                                $token = Cache::remember('lagoon_api_token_'.md5($clientConfig['ssh_server']), now()->addMinutes(2), function () use ($clientConfig) {
                                    $ssh = Ssh::createLagoonConfigured(
                                        $clientConfig['ssh_user'],
                                        $clientConfig['ssh_server'],
                                        $clientConfig['ssh_port'],
                                        $clientConfig['ssh_private_key_file']
                                    );

                                    return $ssh->executeLagoonGetToken();
                                });

                                if (empty($token)) {
                                    return 'Error: Unable to authenticate with Lagoon';
                                }

                                $client = app()->makeWith(Client::class, ['config' => $clientConfig]);
                                $client->setLagoonToken($token);
                                $client->initGraphqlClient();

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
                    $instanceUuid = $record->getKeyValue('uuid');
                    $environment = $data['environment'] ?? ($record->getKeyValue('lagoon-deploy-branch') ?: 'main');

                    if (empty($environment)) {
                        Notification::make()
                            ->title('Deployment Failed')
                            ->danger()
                            ->body('Missing branch')
                            ->send();

                        return;
                    }

                    $exitCode = Artisan::call('polydock:app-instance:trigger-deploy', [
                        'instance_uuid' => $instanceUuid,
                        '--environment' => $environment,
                        '--force' => true,
                    ]);

                    $output = Artisan::output();

                    if ($exitCode === 0) {
                        Notification::make()
                            ->title('Deployment Triggered')
                            ->success()
                            ->body($output ?: "Deployment triggered for branch {$environment}")
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Deployment Failed')
                            ->danger()
                            ->body($output ?: 'Failed to trigger deployment')
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
