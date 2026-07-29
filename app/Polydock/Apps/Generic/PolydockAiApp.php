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

#[PolydockAppTitle('Generic Lagoon AI App')]
#[PolydockAppStoreFields]
#[PolydockAppInstanceFields]
class PolydockAiApp extends PolydockApp implements HasAppInstanceFormFields, HasStoreAppFormFields
{
    use UsesAmazeeAiBackend;

    protected AmazeeAiBackendClient $amazeeAiBackendClient;

    protected bool $requiresAiInfrastructure = true;

    private PolydockServiceProviderInterface $amazeeAiBackendClientProvider;

    // Store/instance form-schema methods are inherited from PolydockAppBase (all default to []).
    // The #[PolydockAppStoreFields]/#[PolydockAppInstanceFields] attributes above make the
    // discovery service surface them; concrete AI apps override the schema methods as needed.
}
