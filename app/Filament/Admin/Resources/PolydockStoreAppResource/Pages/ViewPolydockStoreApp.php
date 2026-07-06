<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PolydockStoreAppResource\Pages;

use App\Filament\Admin\Resources\PolydockStoreAppResource;
use App\Jobs\EnsureUnallocatedAppInstancesJob;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPolydockStoreApp extends ViewRecord
{
    protected static string $resource = PolydockStoreAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('downscale_unallocated')
                ->label('Downscale Pre-warm')
                ->icon('heroicon-o-arrow-trending-down')
                ->color('danger')
                ->visible(fn (): bool => $this->record->removableUnallocatedInstancesQuery()->exists())
                ->requiresConfirmation()
                ->schema([
                    Placeholder::make('current_unallocated')
                        ->label('Current Removable Unallocated Instances')
                        ->content(fn () => (string) $this->record->removableUnallocatedInstancesQuery()->count()),
                    Placeholder::make('target_unallocated')
                        ->label('Target Unallocated Instances')
                        ->content(fn () => (string) $this->record->target_unallocated_app_instances),
                    TextInput::make('count')
                        ->label('Instances to Remove')
                        ->numeric()
                        ->minValue(1)
                        ->default(fn () => max(
                            1,
                            min(
                                $this->record->removableUnallocatedInstancesQuery()->count(),
                                max(
                                    1,
                                    $this->record->unallocated_instances_count - $this->record->target_unallocated_app_instances,
                                ),
                            ),
                        ))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $removedCount = $this->record->queueUnallocatedInstancesForRemoval(
                        (int) $data['count'],
                        'Manual pre-warm downscale requested',
                    );

                    activity('audit')
                        ->performedOn($this->record)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'action' => 'filament.downscale_unallocated',
                            'count' => $removedCount,
                        ])
                        ->log('Downscaled pre-warm pool (admin UI)');

                    Notification::make()
                        ->title('Pre-warm Downscale Queued')
                        ->success()
                        ->body("Queued {$removedCount} unallocated instance(s) for removal.")
                        ->send();

                    $this->refreshFormData([
                        'unallocated_instances_count',
                        'target_unallocated_app_instances',
                    ]);
                }),
            Action::make('refresh_stale_unallocated')
                ->label('Refresh Stale Pre-warm')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->record->refreshableUnallocatedInstancesQuery()->exists())
                ->requiresConfirmation()
                ->modalDescription('Queues stale unallocated environments for removal, then the maintenance job recreates them back to target.')
                ->action(function (): void {
                    $queuedCount = $this->record->queueUnallocatedInstancesForRemoval(
                        $this->record->refreshableUnallocatedInstancesQuery()->count(),
                        'Manual stale pre-warm refresh requested',
                        now()->subDays($this->record->refresh_unallocated_instances_after_days),
                    );

                    EnsureUnallocatedAppInstancesJob::dispatch()->onQueue('unallocated-instance-creation');

                    activity('audit')
                        ->performedOn($this->record)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'action' => 'filament.refresh_stale_unallocated',
                            'queued_count' => $queuedCount,
                        ])
                        ->log('Refreshed stale pre-warm instances (admin UI)');

                    Notification::make()
                        ->title('Pre-warm Refresh Queued')
                        ->success()
                        ->body("Queued {$queuedCount} stale unallocated instance(s) for refresh.")
                        ->send();

                    $this->refreshFormData([
                        'unallocated_instances_count',
                        'target_unallocated_app_instances',
                    ]);
                }),
            Action::make('refresh_all_unallocated')
                ->label('Refresh All Pre-warm')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
                ->visible(fn (): bool => $this->record->removableUnallocatedInstancesQuery()->exists())
                ->requiresConfirmation()
                ->modalDescription('Queues all removable pre-warm environments for removal, then dispatches refill maintenance back to target.')
                ->action(function (): void {
                    $queuedCount = $this->record->queueUnallocatedInstancesForRemoval(
                        $this->record->removableUnallocatedInstancesQuery()->count(),
                        'Manual full pre-warm refresh requested',
                    );

                    EnsureUnallocatedAppInstancesJob::dispatch()->onQueue('unallocated-instance-creation');

                    activity('audit')
                        ->performedOn($this->record)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'action' => 'filament.refresh_all_unallocated',
                            'queued_count' => $queuedCount,
                        ])
                        ->log('Refreshed all pre-warm instances (admin UI)');

                    Notification::make()
                        ->title('Full Pre-warm Refresh Queued')
                        ->success()
                        ->body("Queued {$queuedCount} pre-warm instance(s) for refresh.")
                        ->send();

                    $this->refreshFormData([
                        'unallocated_instances_count',
                        'target_unallocated_app_instances',
                    ]);
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load custom field values from app_config JSON column
        // Fields are stored without prefix, so we load them directly with their original names
        $appConfig = $this->record->app_config ?? [];

        foreach ($appConfig as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }
}
