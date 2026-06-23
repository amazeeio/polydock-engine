<?php

declare(strict_types=1);

namespace App\Polydock\Apps\Generic;

use App\Polydock\Apps\Generic\Traits\UsesAmazeeAiBackend;
use App\Polydock\Clients\AmazeeAi\Client as AmazeeAiBackendClient;
use App\Polydock\Core\Attributes\PolydockAppInstanceFields;
use App\Polydock\Core\Attributes\PolydockAppStoreFields;
use App\Polydock\Core\Attributes\PolydockAppTitle;
use App\Polydock\Core\Contracts\HasAppInstanceFormFields;
use App\Polydock\Core\Contracts\HasStoreAppFormFields;
use App\Polydock\Core\PolydockServiceProviderInterface;
use Filament\Forms\Components\Component;

#[PolydockAppTitle('Generic Lagoon AI App')]
#[PolydockAppStoreFields]
#[PolydockAppInstanceFields]
class PolydockAiApp extends PolydockApp implements HasAppInstanceFormFields, HasStoreAppFormFields
{
    use UsesAmazeeAiBackend;

    protected AmazeeAiBackendClient $amazeeAiBackendClient;

    protected bool $requiresAiInfrastructure = true;

    private PolydockServiceProviderInterface $amazeeAiBackendClientProvider;

    /**
     * Get custom form fields for Store App configuration.
     *
     * Override this method in subclasses to provide app-specific configuration fields.
     * See docs/PolydockAiApp.md for example implementations.
     *
     * @return array<Component>
     */
    public static function getStoreAppFormSchema(): array
    {
        return [];
    }

    /**
     * Get infolist schema for displaying Store App configuration.
     *
     * Override this method in subclasses to provide app-specific display fields.
     * See docs/PolydockAiApp.md for example implementations.
     *
     * @return array<\Filament\Infolists\Components\Component>
     */
    public static function getStoreAppInfolistSchema(): array
    {
        return [];
    }

    /**
     * Get custom form fields for App Instance configuration.
     *
     * Inherits from parent and adds AI-specific instance fields.
     * Use array_merge(parent::getAppInstanceFormSchema(), [...]) for inheritance.
     *
     * @return array<Component>
     */
    #[\Override]
    public static function getAppInstanceFormSchema(): array
    {
        return [];
    }

    /**
     * Get infolist schema for displaying App Instance configuration.
     *
     * Inherits from parent and adds AI-specific instance display fields.
     * Use array_merge(parent::getAppInstanceInfolistSchema(), [...]) for inheritance.
     *
     * @return array<\Filament\Infolists\Components\Component>
     */
    #[\Override]
    public static function getAppInstanceInfolistSchema(): array
    {
        return [];
    }
}
