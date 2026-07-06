<?php

namespace App\Polydock\Core;

use App\Polydock\Core\Exceptions\PolydockEngineValidationException;
use App\Polydock\Core\Traits\PolydockAppLoggerTrait;

abstract class PolydockEngineBase implements PolydockEngineInterface
{
    use PolydockAppLoggerTrait;

    /**
     * Validate that an app instance has all required variables
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to validate
     * @return bool True if the app instance has all required variables, false otherwise
     *
     * @throws PolydockEngineValidationException If a required variable is missing
     */
    public function validateAppInstanceHasAllRequiredVariables(PolydockAppInstanceInterface $appInstance): bool
    {
        foreach ($appInstance->getApp()->getVariableDefinitions() as $variableDefinition) {
            // Unset/missing variables default to '', whereas optional or not-always-populated variables can be null.
            if ($appInstance->getKeyValue($variableDefinition->getName()) === '') {
                throw new PolydockEngineValidationException(
                    sprintf(
                        'App instance %s is missing required variable %s',
                        $appInstance->getAppType(),
                        $variableDefinition->getName()
                    )
                );
            }
        }

        return true;
    }
}
