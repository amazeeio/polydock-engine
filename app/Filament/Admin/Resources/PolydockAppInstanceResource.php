<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use App\Filament\Admin\RelationManagers\ActivitiesRelationManager;
use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages\CreatePolydockAppInstance;
use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages\EditPolydockAppInstance;
use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages\ListPolydockAppInstances;
use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages\ViewPolydockAppInstance;
use App\Filament\Admin\Resources\PolydockAppInstanceResource\RelationManagers\LogsRelationManager;
use App\Filament\Exports\UserRemoteRegistrationExporter;
use App\Models\PolydockAppInstance;
use App\Models\PolydockDeploymentRun;
use App\Models\User;
use App\Polydock\Core\Attributes\PolydockAppInstanceFields;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\PolydockEngine\Helpers\AmazeeAiBackendHelper;
use App\PolydockEngine\Helpers\LagoonHelper;
use App\Services\PolydockAppClassDiscovery;
use App\Services\PolydockDeploymentService;
use App\Support\SensitiveDataRedactor;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class PolydockAppInstanceResource extends Resource
{
    protected static ?string $model = PolydockAppInstance::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'App Instances';

    protected static ?int $navigationSort = 100;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Instance Name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
            ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->searchable()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->description(fn ($record) => $record->storeApp->store->name.' - '.$record->storeApp->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('userGroup.name')
                    ->label('User Group')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state, $record) => $record->trashed() ? 'gray' : PolydockAppInstanceStatus::from($state->value)->getColor())
                    ->icon(fn ($state, $record) => $record->trashed() ? 'heroicon-o-archive-box-x-mark' : PolydockAppInstanceStatus::from($state->value)->getIcon())
                    ->formatStateUsing(fn ($state, $record) => $record->trashed() ? 'Purged' : PolydockAppInstanceStatus::from($state->value)->getLabel())
                    ->sortable(),
                TextColumn::make('is_trial')
                    ->state(fn ($record) => $record->is_trial ? 'Yes' : 'No')
                    ->label('Trial'),
                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends At')
                    ->description(fn ($record) => $record->trial_completed ? 'Trial completed' : 'Trial active')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('send_midtrial_email_at')
                    ->label('Midtrial Email')
                    ->description(fn ($record) => $record->midtrial_email_sent ? 'Sent' : 'Pending')
                    ->state(fn ($record) => $record->send_midtrial_email_at
                        ? $record->send_midtrial_email_at->format('Y-m-d H:i:s')
                        : ''),
                TextColumn::make('send_one_day_left_email_at')
                    ->label('1D Left Email')
                    ->description(fn ($record) => $record->one_day_left_email_sent ? 'Sent' : 'Pending')
                    ->state(fn ($record) => $record->send_one_day_left_email_at
                        ? $record->send_one_day_left_email_at->format('Y-m-d H:i:s')
                        : ''),
                TextColumn::make('trial_complete_email_sent')
                    ->label('Trial Complete Email')
                    ->state(fn ($record) => $record->is_trial && $record->trial_complete_email_sent
                        ? 'Sent'
                        : ($record->is_trial ? 'Pending' : '')),
                TextColumn::make('last_deployment_status')
                    ->label('Last Deploy')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'complete', 'completed', 'success' => 'success',
                        'failed', 'failure', 'error', 'cancelled', 'canceled' => 'danger',
                        null, '' => 'gray',
                        default => 'info',
                    })
                    ->description(fn ($record) => $record->last_deployed_at?->diffForHumans())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('next_redeploy_at')
                    ->label('Next Redeploy')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('removed_at')
                    ->dateTime()
                    ->label('Removed At')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purge_eligible_at')
                    ->dateTime()
                    ->label('Purge Eligible')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purge_attempts')
                    ->label('Purge Attempts')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('force_purge_requested_at')
                    ->dateTime()
                    ->label('Force Purge Requested')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(
                        collect(PolydockAppInstanceStatus::cases())
                            ->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])
                            ->toArray(),
                    )
                    ->multiple()
                    ->label('Instance Status')
                    ->indicator('Status'),

                SelectFilter::make('status_group')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'polling' => 'Polling',
                    ])
                    ->query(function ($query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        return $query->where(function ($query) use ($data) {
                            match ($data['value']) {
                                'pending' => $query->whereIn('status', PolydockAppInstance::$pendingStatuses),
                                'completed' => $query->whereIn('status', PolydockAppInstance::$completedStatuses),
                                'failed' => $query->whereIn('status', PolydockAppInstance::$failedStatuses),
                                'polling' => $query->whereIn('status', PolydockAppInstance::$pollingStatuses),
                                default => null,
                            };
                        });
                    })
                    ->label('Status Group')
                    ->indicator('Status Group'),

                SelectFilter::make('stage_group')
                    ->options([
                        'create' => 'Create Stage',
                        'deploy' => 'Deploy Stage',
                        'remove' => 'Remove Stage',
                        'upgrade' => 'Upgrade Stage',
                        'running' => 'Running Stage',
                        'purge' => 'Purge Stage',
                    ])
                    ->query(function ($query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        return $query->where(function ($query) use ($data) {
                            match ($data['value']) {
                                'create' => $query->whereIn('status', PolydockAppInstance::$stageCreateStatuses),
                                'deploy' => $query->whereIn('status', PolydockAppInstance::$stageDeployStatuses),
                                'remove' => $query->whereIn('status', PolydockAppInstance::$stageRemoveStatuses),
                                'upgrade' => $query->whereIn('status', PolydockAppInstance::$stageUpgradeStatuses),
                                'running' => $query->whereIn('status', PolydockAppInstance::$stageRunningStatuses),
                                'purge' => $query->whereIn('status', PolydockAppInstance::$stagePurgeStatuses),
                                default => null,
                            };
                        });
                    })
                    ->label('Stage Group')
                    ->indicator('Stage'),

                SelectFilter::make('is_trial')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ])
                    ->label('Is Trial')
                    ->indicator('Is Trial'),

                SelectFilter::make('store')
                    ->relationship('storeApp.store', 'name')
                    ->label('Store')
                    ->searchable()
                    ->preload()
                    ->indicator('Store'),

                SelectFilter::make('polydock_store_app_id')
                    ->relationship('storeApp', 'name')
                    ->label('Store App')
                    ->searchable()
                    ->preload()
                    ->indicator('Store App'),

                TrashedFilter::make()
                    ->label('Include Purged'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export registrations')
                    ->exporter(UserRemoteRegistrationExporter::class),
            ])
            ->toolbarActions([
                BulkAction::make('redeploy')
                    ->label('Redeploy selected')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (): bool => PolydockDeploymentRun::currentUserCanManage())
                    ->requiresConfirmation()
                    ->modalHeading('Trigger Lagoon redeploy')
                    ->modalDescription('This triggers real Lagoon builds for the eligible selected instances (running & not already deploying). Instances stay online during the rolling redeploy.')
                    ->action(function (Collection $records): void {
                        $run = app(PolydockDeploymentService::class)->redeploy(
                            $records,
                            PolydockDeploymentRunTriggerSourceEnum::MANUAL,
                            auth()->user(),
                        );

                        if ($run === null) {
                            Notification::make()
                                ->warning()
                                ->title('Nothing to redeploy')
                                ->body('None of the selected instances were eligible (must be running and not already deploying).')
                                ->send();

                            return;
                        }

                        if ($run->status === PolydockDeploymentRunStatusEnum::FAILED) {
                            Notification::make()
                                ->danger()
                                ->title('Redeploy failed to trigger')
                                ->body('See logs for details. Run: '.$run->uuid)
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Redeploy triggered')
                            ->body("Triggered {$run->total_count} deployment(s). Bulk id: ".($run->lagoon_bulk_id ?? 'n/a'))
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Instance Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Instance Name'),
                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar')
                                    ->iconColor('gray'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state, $record) => $record->trashed() ? 'gray' : PolydockAppInstanceStatus::from($state->value)->getColor())
                                    ->icon(fn ($state, $record) => $record->trashed() ? 'heroicon-o-archive-box-x-mark' : PolydockAppInstanceStatus::from($state->value)->getIcon())
                                    ->formatStateUsing(
                                        fn ($state, $record) => $record->trashed() ? 'Purged' : PolydockAppInstanceStatus::from($state->value)->getLabel(),
                                    ),
                                TextEntry::make('status_message')
                                    ->label('Status Message'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('storeApp.lagoon_deploy_region_id_ext')
                                    ->label('Deploy Region')
                                    ->formatStateUsing(
                                        fn ($state) => LagoonHelper::getLagoonCodeDataValueForRegion($state, 'name'),
                                    ),
                                TextEntry::make(
                                    'storeApp.amazee_ai_backend_region_id_ext',
                                )
                                    ->label('AI Backend Region')
                                    ->formatStateUsing(
                                        fn ($state) => AmazeeAiBackendHelper::getAmazeeAiBackendCodeDataValueForRegion(
                                            $state,
                                            'name',
                                        ),
                                    ),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('app_url')
                                    ->label('App URL')
                                    ->url(fn ($record) => $record->app_url)
                                    ->openUrlInNewTab()
                                    ->icon('heroicon-m-link')
                                    ->iconColor('primary'),
                            ]),
                    ])
                    ->columnSpan(2),

                Section::make('App & Group')
                    ->schema([
                        TextEntry::make('storeApp.name')
                            ->label('Store App')
                            ->icon('heroicon-m-squares-2x2')
                            ->iconColor('primary'),
                        TextEntry::make('userGroup.name')
                            ->label('User Group')
                            ->visible(fn ($record) => $record->userGroup !== null)
                            ->url(fn ($record) => $record->userGroup ? UserGroupResource::getUrl('view', ['record' => $record->userGroup]) : null)
                            ->openUrlInNewTab()
                            ->icon('heroicon-m-user-group')
                            ->iconColor('success'),
                    ])
                    ->columnSpan(1),

                Section::make('Instance Configuration')
                    ->description('Instance-specific settings configured at creation.')
                    ->schema(self::getRenderedInstanceConfigForRecord(...))
                    ->visible(self::hasInstanceConfigFields(...))
                    ->columnSpan(3)
                    ->collapsible(),

                Section::make('Instance Data')
                    ->description('Safe data that can be shared with webhooks')
                    ->schema(self::getRenderedSafeDataForRecord(...))
                    ->columnSpan(3)
                    ->collapsible(),
            ])
            ->columns(3);
    }

    public static function getRenderedSafeDataForRecord(PolydockAppInstance $record): array
    {
        return [
            Grid::make()
                ->schema([
                    KeyValueEntry::make('data')
                        ->label('')
                        ->state(function (PolydockAppInstance $record) {
                            $safeData = $record->data ?? [];
                            $sensitiveKeys = $record->getSensitiveDataKeys();
                            $redactedData = [];

                            foreach ($safeData as $key => $value) {
                                // Split combined key-value strings like "instance_config_VAR=VALUE"
                                // but skip if it's a URL or if the key is already a non-numeric string.
                                if (\is_int($key) && \is_string($value) && str_contains($value, '=') && ! str_starts_with($value, 'http')) {
                                    [$newKey, $newValue] = explode('=', (string) $value, 2);
                                    $key = $newKey;
                                    $value = $newValue;
                                }

                                if (SensitiveDataRedactor::shouldRedactKey((string) $key, $sensitiveKeys)) {
                                    $value = SensitiveDataRedactor::REDACTED_VALUE;
                                }

                                if ($value === null) {
                                    $value = 'N/A';
                                }

                                if (\is_array($value)) {
                                    $value = json_encode($value);
                                }

                                $redactedData[$key] = $value;
                            }

                            return $redactedData;
                        })
                        ->columnSpan(2),
                ])
                ->columns(3),
        ];
    }

    /**
     * Check if the record's app class defines instance configuration fields.
     */
    public static function hasInstanceConfigFields(PolydockAppInstance $record): bool
    {
        $storeApp = $record->storeApp;
        if (! $storeApp || empty($storeApp->polydock_app_class)) {
            return false;
        }

        $discovery = app(PolydockAppClassDiscovery::class);

        return ! empty($discovery->getAppInstanceInfolistSchema($storeApp->polydock_app_class));
    }

    /**
     * Get rendered infolist components for instance configuration fields.
     *
     * Values are loaded from PolydockVariables associated with the app instance.
     */
    public static function getRenderedInstanceConfigForRecord(PolydockAppInstance $record): array
    {
        $storeApp = $record->storeApp;
        if (! $storeApp || empty($storeApp->polydock_app_class)) {
            return [];
        }

        $discovery = app(PolydockAppClassDiscovery::class);
        $fieldNames = $discovery->getAppInstanceFormFieldNames($storeApp->polydock_app_class);

        if (empty($fieldNames)) {
            return [];
        }

        // Build a simple display of instance config values from PolydockVariables
        $renderedArray = [];
        $instanceConfigPrefix = PolydockAppInstanceFields::FIELD_PREFIX;

        foreach ($fieldNames as $fieldName) {
            $value = $record->getPolydockVariableValue($fieldName);

            // Create a human-readable label from the field name
            // e.g., "instance_config_ai_model_override" -> "Ai Model Override"
            $labelName = str_replace($instanceConfigPrefix, '', $fieldName);
            $labelName = str_replace('_', ' ', $labelName);
            $labelName = ucwords($labelName);

            // Check if value should be masked (for encrypted fields)
            $isEncrypted = $record->isPolydockVariableEncrypted($fieldName);

            $renderedItem = TextEntry::make("instance_config_display_{$fieldName}")
                ->label($labelName);

            if ($isEncrypted && $value !== null && $value !== '') {
                // Mask encrypted values
                $renderedItem->state('********');
            } elseif ($value === null || $value === '') {
                $renderedItem->state('Not configured')
                    ->color('gray');
            } else {
                $renderedItem->state($value);
            }

            $renderedArray[] = $renderedItem;
        }

        return $renderedArray;
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    #[Override]
    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('create', PolydockAppInstance::class) ?? false;
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPolydockAppInstances::route('/'),
            'create' => CreatePolydockAppInstance::route('/create'),
            'view' => ViewPolydockAppInstance::route('/{record}'),
            'edit' => EditPolydockAppInstance::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['storeApp.store', 'userGroup', 'deploymentRun']);

        /** @var User|null $user */
        $user = auth()->user();
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('super_admin') || $user->can('view_any_polydock_app_instance')) {
            return $query;
        }

        return $query->whereIn('user_group_id', $user->groups()->select('user_groups.id'));
    }
}
