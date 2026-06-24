<?php

namespace App\Polydock\Core;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\Traits\PolydockAppConfigurationTrait;
use App\Polydock\Core\Traits\PolydockAppFundamentalsTrait;
use App\Polydock\Core\Traits\PolydockAppLoggerTrait;
use Filament\Forms\Components\Component;

abstract class PolydockAppBase implements PolydockAppInterface
{
    /**
     * The engine instance
     */
    protected PolydockEngineInterface $engine;

    /**
     * The logger instance
     */
    protected PolydockAppLoggerInterface $logger;

    protected string $appName;

    /**
     * A description of what the app does
     */
    protected string $appDescription;

    /**
     * The name of the app's author/creator
     */
    protected string $appAuthor;

    /**
     * The website URL for the app
     */
    protected string $appWebsite;

    /**
     * Email address for app support inquiries
     */
    protected string $appSupportEmail;

    /**
     * Configuration settings for the app
     */
    protected array $appConfiguration;

    /**
     * Whether the app fundamentals have been validated
     */
    private bool $isValidated = false;

    /**
     * Array of variable definitions
     *
     * @var array<PolydockAppVariableDefinitionInterface>
     */
    protected array $variableDefinitions = [];

    use PolydockAppConfigurationTrait;
    use PolydockAppFundamentalsTrait;
    use PolydockAppLoggerTrait;

    /**
     * Initialize a new app instance with fundamental properties
     *
     * @param  string  $appName  The name of the app
     * @param  string  $appDescription  Description of the app
     * @param  string  $appAuthor  Name of the author/creator
     * @param  string  $appWebsite  Website URL for the app
     * @param  string  $appSupportEmail  Support email address
     * @param  array  $variableDefinitions  Array of variable definitions
     */
    final public function __construct($appName, $appDescription, $appAuthor, $appWebsite, $appSupportEmail, array $variableDefinitions = [])
    {
        // Initialize logger using the trait method
        $this->initializeLogger();

        $this->setAppName($appName)
            ->setAppDescription($appDescription)
            ->setAppAuthor($appAuthor)
            ->setAppWebsite($appWebsite)
            ->setAppSupportEmail($appSupportEmail);

        foreach (static::getAppDefaultVariableDefinitions() as $variableDefinition) {
            if (! $variableDefinition instanceof PolydockAppVariableDefinitionInterface) {
                throw new PolydockAppValidationException('Variable definition must implement PolydockAppVariableDefinitionInterface');
            }

            $this->info('Adding default variable definition '.$variableDefinition->getName());
            $this->addVariableDefinition($variableDefinition);
        }

        foreach ($variableDefinitions as $variableDefinition) {
            if (! $variableDefinition instanceof PolydockAppVariableDefinitionInterface) {
                throw new PolydockAppValidationException('Variable definition must implement PolydockAppVariableDefinitionInterface');
            }

            if (! $this->getVariableDefinition($variableDefinition->getName())) {
                $this->info('Adding constructor variable definition '.$variableDefinition->getName());
                $this->addVariableDefinition($variableDefinition);
            } else {
                $this->warning('Variable definition '.$variableDefinition->getName().' already exists, overwriting');
                $this->addVariableDefinition($variableDefinition);
            }
        }

        $this->validateAppFundamentals();
    }

    public function addVariableDefinition(PolydockAppVariableDefinitionInterface $variableDefinition): self
    {
        if ($this->getVariableDefinition($variableDefinition->getName())) {
            unset($this->variableDefinitions[$variableDefinition->getName()]);
        }

        $this->variableDefinitions[$variableDefinition->getName()] = $variableDefinition;

        return $this;
    }

    public function getVariableDefinitions(): array
    {
        return $this->variableDefinitions;
    }

    public function getVariableDefinition(string $name): ?PolydockAppVariableDefinitionInterface
    {
        return $this->variableDefinitions[$name] ?? null;
    }

    /**
     * Validates that all required fundamental app properties are set
     *
     * @return PolydockAppInterface Returns the instance for method chaining
     *
     * @throws PolydockAppValidationException if any required property is empty
     */
    private function validateAppFundamentals(): PolydockAppInterface
    {
        if (empty($this->appName)) {
            throw new PolydockAppValidationException('App name is required');
        }

        if (empty($this::getAppVersion())) {
            throw new PolydockAppValidationException('App version is required');
        }

        if (empty($this->appDescription)) {
            throw new PolydockAppValidationException('App description is required');
        }

        if (empty($this->appAuthor)) {
            throw new PolydockAppValidationException('App author is required');
        }

        if (empty($this->appWebsite)) {
            throw new PolydockAppValidationException('App website is required');
        }

        if (empty($this->appSupportEmail)) {
            throw new PolydockAppValidationException('App support email is required');
        }

        $this->isValidated = true;

        return $this;
    }

    public function getEngine(): PolydockEngineInterface
    {
        return $this->engine;
    }

    public function setEngine(PolydockEngineInterface $engine): self
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * Validates that the app instance status is as expected
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to validate
     * @param  PolydockAppInstanceStatus  $expectedStatus  The expected status
     * @return bool True if the status is as expected, false otherwise
     *
     * @throws PolydockAppInstanceStatusFlowException if the status is not as expected
     */
    public function validateAppInstanceStatusIsExpected(
        PolydockAppInstanceInterface $appInstance,
        PolydockAppInstanceStatus $expectedStatus): bool
    {
        if ($appInstance->getStatus() !== $expectedStatus) {
            throw new PolydockAppInstanceStatusFlowException('App instance is not in the expected status. '
            .'Wanted '.$expectedStatus->value.' but found '.$appInstance->getStatus()->value);
        }

        return true;
    }

    /**
     * Get custom form fields for Store App configuration.
     *
     * Override this method in subclasses to provide app-specific configuration fields
     * that appear when creating/editing a Store App in the admin panel.
     *
     * Field names should use snake_case without any prefix - the 'app_config_' prefix
     * is added automatically when the schema is processed.
     *
     * Use the 'encrypted' extraAttribute to mark sensitive fields for encrypted storage:
     *   ->extraAttributes(['encrypted' => true])
     *
     * Classes using this method should also:
     * - Add the #[PolydockAppStoreFields] attribute to the class
     * - Implement the HasStoreAppFormFields interface
     *
     * @return array<Component> Array of Filament form components
     *
     * @example
     * ```php
     * public static function getStoreAppFormSchema(): array
     * {
     *     return [
     *         Forms\Components\Section::make('API Settings')
     *             ->schema([
     *                 Forms\Components\TextInput::make('api_endpoint')
     *                     ->label('API Endpoint')
     *                     ->url()
     *                     ->required(),
     *                 Forms\Components\TextInput::make('api_key')
     *                     ->label('API Key')
     *                     ->password()
     *                     ->extraAttributes(['encrypted' => true]),
     *             ]),
     *     ];
     * }
     * ```
     */
    public static function getStoreAppFormSchema(): array
    {
        return [];
    }

    /**
     * Get infolist schema for displaying Store App configuration.
     *
     * Override this method in subclasses to provide app-specific display fields
     * that appear when viewing a Store App in the admin panel.
     *
     * Field names should match those in getStoreAppFormSchema() (without prefix).
     * The 'app_config_' prefix is added automatically when the schema is processed.
     *
     * Classes using this method should also:
     * - Add the #[PolydockAppStoreFields] attribute to the class
     * - Implement the HasStoreAppFormFields interface
     *
     * @return array<\Filament\Infolists\Components\Component> Array of Filament infolist components
     *
     * @example
     * ```php
     * public static function getStoreAppInfolistSchema(): array
     * {
     *     return [
     *         Infolists\Components\Section::make('API Settings')
     *             ->schema([
     *                 Infolists\Components\TextEntry::make('api_endpoint')
     *                     ->label('API Endpoint'),
     *                 Infolists\Components\TextEntry::make('api_key')
     *                     ->label('API Key')
     *                     ->formatStateUsing(fn ($state) => $state ? '••••••••' : 'Not set'),
     *             ]),
     *     ];
     * }
     * ```
     */
    public static function getStoreAppInfolistSchema(): array
    {
        return [];
    }

    /**
     * Get custom form fields for App Instance configuration.
     *
     * Override this method in subclasses to provide instance-specific configuration fields
     * that appear when creating an App Instance in the admin panel.
     *
     * Field names should use snake_case without any prefix - the 'instance_config_' prefix
     * is added automatically when the schema is processed.
     *
     * Use array_merge(parent::getAppInstanceFormSchema(), [...]) in child classes
     * to support field inheritance from parent classes.
     *
     * Use the 'encrypted' extraAttribute to mark sensitive fields for encrypted storage:
     *   ->extraAttributes(['encrypted' => true])
     *
     * Classes using this method should also:
     * - Add the #[PolydockAppInstanceFields] attribute to the class
     * - Implement the HasAppInstanceFormFields interface
     *
     * @return array<Component> Array of Filament form components
     *
     * @example
     * ```php
     * public static function getAppInstanceFormSchema(): array
     * {
     *     return array_merge(parent::getAppInstanceFormSchema(), [
     *         Forms\Components\Section::make('Instance Settings')
     *             ->schema([
     *                 Forms\Components\TextInput::make('custom_domain')
     *                     ->label('Custom Domain')
     *                     ->url(),
     *                 Forms\Components\Toggle::make('enable_analytics')
     *                     ->label('Enable Analytics')
     *                     ->default(true),
     *             ]),
     *     ]);
     * }
     * ```
     */
    public static function getAppInstanceFormSchema(): array
    {
        return [];
    }

    /**
     * Get infolist schema for displaying App Instance configuration.
     *
     * Override this method in subclasses to provide instance-specific display fields
     * that appear when viewing an App Instance in the admin panel.
     *
     * Field names should match those in getAppInstanceFormSchema() (without prefix).
     * The 'instance_config_' prefix is added automatically when the schema is processed.
     *
     * Use array_merge(parent::getAppInstanceInfolistSchema(), [...]) in child classes
     * to support field inheritance from parent classes.
     *
     * Classes using this method should also:
     * - Add the #[PolydockAppInstanceFields] attribute to the class
     * - Implement the HasAppInstanceFormFields interface
     *
     * @return array<\Filament\Infolists\Components\Component> Array of Filament infolist components
     *
     * @example
     * ```php
     * public static function getAppInstanceInfolistSchema(): array
     * {
     *     return array_merge(parent::getAppInstanceInfolistSchema(), [
     *         Infolists\Components\Section::make('Instance Settings')
     *             ->schema([
     *                 Infolists\Components\TextEntry::make('custom_domain')
     *                     ->label('Custom Domain'),
     *                 Infolists\Components\IconEntry::make('enable_analytics')
     *                     ->label('Analytics Enabled')
     *                     ->boolean(),
     *             ]),
     *     ]);
     * }
     * ```
     */
    public static function getAppInstanceInfolistSchema(): array
    {
        return [];
    }
}
