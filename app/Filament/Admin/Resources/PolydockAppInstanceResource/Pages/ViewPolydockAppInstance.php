<?php

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Support\Facades\Process;

class ViewPolydockAppInstance extends ViewRecord
{
    protected static string $resource = PolydockAppInstanceResource::class;

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
                    TextInput::make('branch')
                        ->label('Branch / Environment')
                        ->required()
                        ->disabled()
                        ->default(fn ($record) => $record->getKeyValue('lagoon-deploy-branch')),
                    Placeholder::make('last_deployment_date')
                        ->label('Last Deployment Date')
                        ->content(function ($record) {
                            $lastDeployLog = $record->logs()
                                ->whereJsonContains('data->new_status', PolydockAppInstanceStatus::DEPLOY_COMPLETED->value)
                                ->latest()
                                ->first();

                            return $lastDeployLog ? $lastDeployLog->created_at->toDateTimeString() : 'Never';
                        }),
                ])
                ->action(function (array $data, $record): void {
                    $projectName = $record->getKeyValue('lagoon-project-name');
                    $branch = $data['branch'];

                    if (empty($projectName) || empty($branch)) {
                        Notification::make()
                            ->title('Deployment Failed')
                            ->danger()
                            ->body('Missing project name or branch configuration.')
                            ->send();

                        return;
                    }

                    $fullCommand = sprintf('lagoon deploy -p %s -e %s',
                        escapeshellarg($projectName),
                        escapeshellarg($branch)
                    );

                    $result = Process::run($fullCommand);

                    if ($result->successful()) {
                        Notification::make()
                            ->title('Deployment Triggered')
                            ->success()
                            ->body('Output: '.trim($result->output()))
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Deployment Failed')
                            ->danger()
                            ->body('Error: '.trim($result->errorOutput()))
                            ->send();
                    }
                }),
        ];
    }
}
