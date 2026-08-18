<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use App\Enums\PolydockStoreAppStatusEnum;
use App\Filament\Admin\Resources\PolydockStoreAppResource\Pages\CreatePolydockStoreApp;
use App\Filament\Admin\Resources\PolydockStoreAppResource\Pages\EditPolydockStoreApp;
use App\Filament\Admin\Resources\PolydockStoreAppResource\Pages\ListPolydockStoreApps;
use App\Filament\Admin\Resources\PolydockStoreAppResource\Pages\ViewPolydockStoreApp;
use App\Filament\Admin\Resources\PolydockStoreAppResource\RelationManagers\PreWarmInstancesRelationManager;
use App\Models\PolydockAppInstance;
use App\Models\PolydockDeploymentRun;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Services\PolydockAppClassDiscovery;
use App\Services\PolydockDeploymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PolydockStoreAppResource extends Resource
{
    protected static ?string $model = PolydockStoreApp::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|UnitEnum|null $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Apps';

    protected static ?int $navigationSort = 5100;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('polydock_store_id')
                    ->label('Store')
                    ->options(PolydockStore::all()->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->disabled(fn (?PolydockStoreApp $record): bool => $record && $record->instances()->exists())
                    ->dehydrated(fn (?PolydockStoreApp $record): bool => ! $record || ! $record->instances()->exists()),
                Select::make('polydock_app_class')
                    ->label('Polydock App Class')
                    ->options(fn () => app(PolydockAppClassDiscovery::class)->getAvailableAppClasses())
                    ->required()
                    ->searchable()
                    ->live(onBlur: false)
                    ->afterStateUpdated(function (Set $set, ?string $old): void {
                        if ($old) {
                            $fieldNames = app(PolydockAppClassDiscovery::class)
                                ->getStoreAppFormFieldNames($old);
                            foreach ($fieldNames as $fieldName) {
                                $set($fieldName, null);
                            }
                        }
                    })
                    ->helperText('The application class that controls deployment and lifecycle behaviour.')
                    ->disabled(fn (?PolydockStoreApp $record): bool => $record && $record->instances()->exists())
                    ->dehydrated(fn (?PolydockStoreApp $record): bool => ! $record || ! $record->instances()->exists()),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('polydock_product_type_id')
                    ->label('Product Type')
                    ->relationship('productType', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->unique('polydock_product_types', 'name'),
                    ]),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('author')
                    ->required()
                    ->maxLength(255),
                TextInput::make('website')
                    ->required()
                    ->maxLength(255),
                TextInput::make('support_email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('lagoon_deploy_git')
                    ->required()
                    ->maxLength(255),
                TextInput::make('lagoon_deploy_branch')
                    ->required()
                    ->maxLength(255)
                    ->default('main'),
                Select::make('status')
                    ->options(PolydockStoreAppStatusEnum::class)
                    ->required(),
                TextInput::make('target_unallocated_app_instances')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Ignored when Project Naming is set to Custom - custom-named apps cannot be pre-warmed.'),
                Toggle::make('available_for_trials')
                    ->label('Available for Trials')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('listed_in_marketplace')
                    ->label('Listed in Marketplace')
                    ->helperText('Controls whether this app appears in the public /api/regions listing.')
                    ->default(false)
                    ->columnSpanFull(),
                Section::make('Pre-warm Settings')
                    ->description('Controls how unallocated pre-warm instances are refreshed over time.')
                    ->schema([
                        Toggle::make('refresh_unallocated_instances')
                            ->label('Refresh stale pre-warm instances')
                            ->default(false)
                            ->live(),
                        TextInput::make('refresh_unallocated_instances_after_days')
                            ->label('Refresh After (Days)')
                            ->numeric()
                            ->minValue(1)
                            ->default(7)
                            ->required(fn (Get $get): bool => (bool) $get('refresh_unallocated_instances'))
                            ->visible(fn (Get $get): bool => (bool) $get('refresh_unallocated_instances')),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('Project Naming')
                    ->description('How Lagoon project names are generated for instances of this app.')
                    ->schema([
                        Select::make('project_naming_mode')
                            ->label('Naming Mode')
                            ->options([
                                PolydockStoreApp::PROJECT_NAMING_MODE_PATTERN => 'Pattern - generated from word lists',
                                PolydockStoreApp::PROJECT_NAMING_MODE_CUSTOM => 'Custom - name supplied at registration (no pre-warming)',
                            ])
                            ->default(PolydockStoreApp::PROJECT_NAMING_MODE_PATTERN)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('project_naming_prefix')
                            ->label('App Prefix')
                            ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')
                            ->maxLength(30)
                            ->helperText('Optional. Prepended to the store prefix: <app-prefix>-<store-prefix>-<adjective>-<noun>-<id>. Leave empty to use the store prefix alone.')
                            ->visible(fn (Get $get): bool => $get('project_naming_mode') !== PolydockStoreApp::PROJECT_NAMING_MODE_CUSTOM),
                        Placeholder::make('store_project_prefix')
                            ->label('Store Prefix (set on the store, not editable here)')
                            ->content(fn (Get $get): string => PolydockStore::find($get('polydock_store_id'))->lagoon_deploy_project_prefix ?? '—')
                            ->visible(fn (Get $get): bool => $get('project_naming_mode') !== PolydockStoreApp::PROJECT_NAMING_MODE_CUSTOM),
                        TagsInput::make('project_naming_adjectives')
                            ->label('Adjective Word List')
                            ->placeholder('e.g. snappy, zesty, jolly')
                            ->helperText('Optional. Names are <prefix>-<adjective>-<noun>-<id>. Leave empty to use the generic color list.')
                            ->visible(fn (Get $get): bool => $get('project_naming_mode') !== PolydockStoreApp::PROJECT_NAMING_MODE_CUSTOM),
                        TagsInput::make('project_naming_nouns')
                            ->label('Noun Word List')
                            ->placeholder('e.g. lobster, shrimp, crab')
                            ->helperText('Optional. Leave empty to use the generic animal list.')
                            ->visible(fn (Get $get): bool => $get('project_naming_mode') !== PolydockStoreApp::PROJECT_NAMING_MODE_CUSTOM),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('Custom Lagoon Route')
                    ->description('Registers a LAGOON_ROUTES_JSON custom route per instance before its first deploy. Use this to set ingress annotations (upload size, timeouts) that Lagoon does not support on autogenerated routes. The custom route becomes the primary route.')
                    ->schema([
                        Toggle::make('lagoon_custom_route_enabled')
                            ->label('Enable custom route')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('lagoon_custom_route_domain_pattern')
                            ->label('Domain Pattern')
                            ->placeholder('{project}.example.amazee.io')
                            ->helperText('Placeholders: {project}, {environment}. Must resolve under the target cluster\'s wildcard DNS.')
                            ->required(fn (Get $get): bool => (bool) $get('lagoon_custom_route_enabled'))
                            ->visible(fn (Get $get): bool => (bool) $get('lagoon_custom_route_enabled')),
                        TextInput::make('lagoon_custom_route_service')
                            ->label('Lagoon Service')
                            ->placeholder('anythingllm')
                            ->helperText('The docker-compose service the route points at.')
                            ->required(fn (Get $get): bool => (bool) $get('lagoon_custom_route_enabled'))
                            ->visible(fn (Get $get): bool => (bool) $get('lagoon_custom_route_enabled')),
                        KeyValue::make('lagoon_custom_route_annotations')
                            ->label('Ingress Annotations')
                            ->keyLabel('Annotation')
                            ->valueLabel('Value')
                            ->helperText('e.g. nginx.ingress.kubernetes.io/proxy-body-size => 0, nginx.ingress.kubernetes.io/proxy-read-timeout => 600')
                            ->visible(fn (Get $get): bool => (bool) $get('lagoon_custom_route_enabled'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('Redeploy Schedule')
                    ->description('Automatically redeploy running instances of this app on a cadence (upgrade rollouts). Trials are never auto-redeployed.')
                    ->schema([
                        Toggle::make('redeploy_enabled')
                            ->label('Enable scheduled redeploys')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('redeploy_interval_days')
                            ->label('Redeploy every (days)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Default cadence for all instances of this app.')
                            ->required(fn (Get $get): bool => (bool) $get('redeploy_enabled'))
                            ->visible(fn (Get $get): bool => (bool) $get('redeploy_enabled')),
                        TextInput::make('beta_redeploy_interval_days')
                            ->label('Beta redeploy every (days)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Optional shorter cadence for instances owned by beta groups. Leave blank to use the default.')
                            ->visible(fn (Get $get): bool => (bool) $get('redeploy_enabled')),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('Lagoon Runtime Settings')
                    ->description('Configuration used by app instance creation for Lagoon runtime behavior.')
                    ->schema([
                        Select::make('lagoon_auto_idle')
                            ->label('Lagoon Auto Idle')
                            ->options([
                                0 => '0 - Off',
                                1 => '1 - On (4-hour auto-idle)',
                            ])
                            ->default(0)
                            ->helperText('See https://docs.lagoon.sh/concepts-advanced/environment-idling/'),
                        TextInput::make('lagoon_production_environment')
                            ->label('Lagoon Production Environment')
                            ->default('main')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Lagoon environment name considered production (for example: main).'),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('Lagoon Scripts')
                    ->description('Scripts to be executed at various stages of the application lifecycle.')
                    ->schema(self::lagoonScriptFormSections())
                    ->collapsible()
                    ->collapsed(),
                Section::make('App-Specific Configuration')
                    ->description('These fields are defined by the selected App Class and will be configurable for this Store App.')
                    ->schema(fn (Get $get): array => app(PolydockAppClassDiscovery::class)
                        ->getStoreAppFormSchema($get('polydock_app_class') ?? ''))
                    ->visible(fn (Get $get): bool => ! empty(app(PolydockAppClassDiscovery::class)
                        ->getStoreAppFormSchema($get('polydock_app_class') ?? '')))
                    ->collapsible()
                    ->collapsed(false)
                    ->columnSpanFull(),
                Placeholder::make('no_app_specific_fields')
                    ->label('')
                    ->content('The selected App Class does not define any app-specific configuration fields.')
                    ->visible(fn (Get $get): bool => ! empty($get('polydock_app_class')) &&
                        empty(app(PolydockAppClassDiscovery::class)->getStoreAppFormSchema($get('polydock_app_class') ?? '')))
                    ->columnSpanFull(),
                Section::make('Instance Ready Email Configuration')
                    ->schema([
                        Select::make('mail_theme')
                            ->label('Email Theme')
                            ->options(fn (): array => collect(config('mail.mjml-config.themes', []))
                                ->map(fn (array $theme, string $key): string => $theme['name'] ?? $key)
                                ->all())
                            ->placeholder('Default theme')
                            ->helperText('Leave blank to use the default email theme')
                            ->columnSpanFull(),

                        TextInput::make('email_subject_line')
                            ->label('Email Subject Line')
                            ->placeholder('Your {app name} Instance is Ready')
                            ->helperText('Leave blank to use default subject')
                            ->columnSpanFull(),

                        MarkdownEditor::make('email_body_markdown')
                            ->label('Email Body Content')
                            ->placeholder('Enter custom content for the "What to Know About Your App" section')
                            ->helperText('This content will appear between the access details and signature')
                            ->toolbarButtons([
                                'bold',
                                'bulletList',
                                'heading',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'undo',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                Section::make('Trial Settings')
                    ->schema([
                        TextInput::make('trial_duration_days')
                            ->label('Trial Duration (Days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365),

                        Grid::make(2)
                            ->schema([
                                ...self::trialEmailFormSections(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('productType.name')
                    ->label('Product Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status'),
                IconColumn::make('available_for_trials')
                    ->label('Trials')
                    ->boolean(),
                IconColumn::make('listed_in_marketplace')
                    ->label('Listed')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('target_unallocated_app_instances')
                    ->label('Target Unallocated')
                    ->sortable(),
                TextColumn::make('unallocated_instances_count')
                    ->label('Unallocated')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('allocated_instances_count')
                    ->label('Allocated')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('redeploy_all')
                    ->label('Redeploy all')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (): bool => PolydockDeploymentRun::currentUserCanManage())
                    ->requiresConfirmation()
                    ->modalHeading('Redeploy all running instances')
                    ->modalDescription('Triggers a Lagoon redeploy for every eligible running instance of this app (skipping any already deploying).')
                    ->action(function (PolydockStoreApp $record): void {
                        $instances = $record->instances()
                            ->whereIn('status', PolydockAppInstance::$redeployEligibleStatuses)
                            ->with('deploymentRun')
                            ->get();

                        $run = app(PolydockDeploymentService::class)->redeploy(
                            $instances,
                            PolydockDeploymentRunTriggerSourceEnum::MANUAL,
                            auth()->user(),
                        );

                        if ($run === null) {
                            Notification::make()
                                ->warning()
                                ->title('Nothing to redeploy')
                                ->body('No eligible running instances for this app.')
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
                            ->body("Triggered {$run->total_count} deployment(s).")
                            ->send();
                    }),
                DeleteAction::make()
                    ->hidden(fn (PolydockStoreApp $record): bool => $record->instances()->exists()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn (): true => true), // Disable bulk delete entirely
                ]),
            ]);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            PreWarmInstancesRelationManager::class,
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPolydockStoreApps::route('/'),
            'create' => CreatePolydockStoreApp::route('/create'),
            'view' => ViewPolydockStoreApp::route('/{record}'),
            'edit' => EditPolydockStoreApp::route('/{record}/edit'),
        ];
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('App Details')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('App Name'),
                                TextEntry::make('store.name')
                                    ->label('Store')
                                    ->icon('heroicon-m-building-storefront')
                                    ->iconColor('primary'),
                                TextEntry::make('productType.name')
                                    ->label('Product Type')
                                    ->placeholder('None'),
                                TextEntry::make('status')
                                    ->badge(),
                            ]),
                        TextEntry::make('description')
                            ->markdown()
                            ->columnSpanFull()
                            ->hidden(fn ($record): bool => blank($record->description)),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('lagoon_deploy_git')
                                    ->copyable()
                                    ->label('Git Repository')
                                    ->icon('heroicon-m-code-bracket')
                                    ->iconColor('gray')
                                    ->columnSpan(2),
                                TextEntry::make('lagoon_deploy_branch')
                                    ->label('Deploy Branch')
                                    ->icon('heroicon-m-code-bracket-square')
                                    ->iconColor('warning'),
                            ]),
                    ])
                    ->columnSpan(2),

                Section::make('Instance Management')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('unallocated_instances_count')
                                    ->label('Unallocated Instances')
                                    ->icon('heroicon-m-queue-list')
                                    ->iconColor('warning'),
                                TextEntry::make('target_unallocated_app_instances')
                                    ->label('Target Unallocated Instances')
                                    ->icon('heroicon-m-queue-list')
                                    ->iconColor('warning'),
                                IconEntry::make('refresh_unallocated_instances')
                                    ->label('Refresh Stale Pre-warm')
                                    ->boolean(),
                                TextEntry::make('refresh_unallocated_instances_after_days')
                                    ->label('Pre-warm Refresh After (Days)'),
                                TextEntry::make('allocatedInstances')
                                    ->label('Allocated Instances')
                                    // getEloquentQuery() already eager-counts this.
                                    ->state(fn ($record) => $record->allocated_instances_count)
                                    ->icon('heroicon-m-check-circle')
                                    ->iconColor('success'),
                                TextEntry::make('lagoon_production_environment')
                                    ->label('Lagoon Production Environment')
                                    ->icon('heroicon-m-flag')
                                    ->iconColor('primary'),
                                TextEntry::make('lagoon_auto_idle')
                                    ->label('Lagoon Auto Idle')
                                    ->icon('heroicon-m-clock')
                                    ->iconColor('gray'),
                            ]),
                    ])
                    ->columnSpan(1),

                Section::make('Lagoon Scripts')
                    ->schema([
                        Grid::make(2)
                            ->schema(self::lagoonScriptInfolistEntries()),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),

                Section::make('App-Specific Configuration')
                    ->schema(fn ($record): array => app(PolydockAppClassDiscovery::class)
                        ->getStoreAppInfolistSchema($record->polydock_app_class ?? ''))
                    ->visible(fn ($record): bool => ! empty(app(PolydockAppClassDiscovery::class)
                        ->getStoreAppInfolistSchema($record->polydock_app_class ?? '')))
                    ->collapsible()
                    ->columnSpan(3),

                Section::make('Support Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('author')
                                    ->icon('heroicon-m-user')
                                    ->iconColor('primary'),
                                TextEntry::make('support_email')
                                    ->icon('heroicon-m-envelope')
                                    ->iconColor('success'),
                            ]),
                        TextEntry::make('website')
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab()
                            ->icon('heroicon-m-globe-alt')
                            ->iconColor('info'),
                    ])
                    ->columnSpan(3),

                Section::make('Trial Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                IconEntry::make('available_for_trials')
                                    ->label('Available for Trials')
                                    ->boolean(),
                                IconEntry::make('listed_in_marketplace')
                                    ->label('Listed in Marketplace')
                                    ->boolean(),
                                TextEntry::make('trial_duration_days')
                                    ->label('Trial Duration')
                                    ->suffix(' days')
                                    ->placeholder('Not set'),
                            ]),
                    ])
                    ->columnSpan(3),

                ...self::trialEmailInfolistSections(),
            ])
            ->columns(3);
    }

    /** Lifecycle script prefixes shared by the form and infolist Lagoon Scripts sections. */
    private const array LAGOON_SCRIPT_STAGES = [
        'post_deploy' => 'Post Deploy',
        'pre_upgrade' => 'Pre Upgrade',
        'upgrade' => 'Upgrade',
        'post_upgrade' => 'Post Upgrade',
        'claim' => 'Claim',
        'pre_remove' => 'Pre Remove',
        'remove' => 'Remove',
    ];

    /** Trial email prefixes shared by the form and infolist trial sections. */
    private const array TRIAL_EMAILS = [
        'midtrial' => 'Mid-trial Email',
        'one_day_left' => 'One Day Left Email',
        'trial_complete' => 'Trial Complete Email',
    ];

    /**
     * @return array<Section>
     */
    private static function lagoonScriptFormSections(): array
    {
        return collect(self::LAGOON_SCRIPT_STAGES)
            ->map(fn (string $label, string $stage): Section => Section::make($label)
                ->collapsed()
                ->collapsible()
                ->schema([
                    Textarea::make("lagoon_{$stage}_script")
                        ->label('Script')
                        ->rows(3),
                    Grid::make(2)
                        ->schema([
                            TextInput::make("lagoon_{$stage}_service")
                                ->label('Service')
                                ->placeholder('cli'),
                            TextInput::make("lagoon_{$stage}_container")
                                ->label('Container')
                                ->placeholder('cli'),
                        ]),
                ]))
            ->values()
            ->all();
    }

    /**
     * @return array<TextEntry>
     */
    private static function lagoonScriptInfolistEntries(): array
    {
        return collect(self::LAGOON_SCRIPT_STAGES)
            ->flatMap(fn (string $label, string $stage): array => [
                TextEntry::make("lagoon_{$stage}_script")
                    ->label("{$label} Script")
                    ->columnSpanFull()
                    ->hidden(fn ($record): bool => blank($record->{"lagoon_{$stage}_script"})),
                TextEntry::make("lagoon_{$stage}_service")
                    ->label("{$label} Service")
                    ->hidden(fn ($record): bool => blank($record->{"lagoon_{$stage}_script"})),
                TextEntry::make("lagoon_{$stage}_container")
                    ->label("{$label} Container")
                    ->hidden(fn ($record): bool => blank($record->{"lagoon_{$stage}_script"})),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<Section>
     */
    private static function trialEmailFormSections(): array
    {
        return collect(self::TRIAL_EMAILS)
            ->map(fn (string $label, string $prefix): Section => Section::make($label)
                ->schema([
                    Toggle::make("send_{$prefix}_email")
                        ->label("Send {$label}"),
                    TextInput::make("{$prefix}_email_subject")
                        ->label('Subject Line')
                        ->maxLength(255),
                    MarkdownEditor::make("{$prefix}_email_markdown")
                        ->label('Email Content')
                        ->columnSpanFull(),
                ]))
            ->values()
            ->all();
    }

    /**
     * @return array<Section>
     */
    private static function trialEmailInfolistSections(): array
    {
        return collect(self::TRIAL_EMAILS)
            ->map(fn (string $label, string $prefix): Section => Section::make($label)
                ->schema([
                    Grid::make(2)
                        ->schema([
                            IconEntry::make("send_{$prefix}_email")
                                ->label('Email Enabled')
                                ->boolean(),
                            TextEntry::make("{$prefix}_email_subject")
                                ->label('Subject Line')
                                ->visible(fn ($record) => $record->{"send_{$prefix}_email"})
                                ->placeholder('Not configured'),
                        ]),
                ])
                ->columnSpan(3))
            ->values()
            ->all();
    }

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['store', 'productType'])
            ->withCount([
                'allocatedInstances',
                'instances as unallocated_instances_count' => function ($query): void {
                    $query->whereNull('user_group_id')
                        ->where(function ($q): void {
                            $q->where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED)
                                ->orWhereIn('status', PolydockAppInstance::unallocatedInProgressStatuses());
                        });
                },
            ]);
    }
}
