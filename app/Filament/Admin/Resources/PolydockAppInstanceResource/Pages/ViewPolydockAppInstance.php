<?php

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;

use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Support\Facades\Process;

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
