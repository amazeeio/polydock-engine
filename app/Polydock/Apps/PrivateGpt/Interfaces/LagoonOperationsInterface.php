<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Interfaces;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;

interface LagoonOperationsInterface
{
    /**
     * Validate app instance and setup Lagoon client with various configuration options
     *
     * @param  array<string, mixed>  $logContext
     */
    public function validateAndSetupLagoon(
        PolydockAppInstanceInterface $appInstance,
        PolydockAppInstanceStatus $expectedStatus,
        array $logContext = [],
        bool $testLagoonPing = true,
        bool $verifyLagoonValuesAreAvailable = true,
        bool $verifyLagoonProjectNameIsAvailable = true,
        bool $verifyLagoonProjectIdIsAvailable = true
    ): void;

    /**
     * Add or update a Lagoon project variable
     */
    public function addOrUpdateLagoonProjectVariable(
        PolydockAppInstanceInterface $appInstance,
        string $variableName,
        string $variableValue,
        string $variableScope
    ): void;
}
