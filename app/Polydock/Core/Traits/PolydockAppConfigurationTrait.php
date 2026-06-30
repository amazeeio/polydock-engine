<?php

namespace App\Polydock\Core\Traits;

trait PolydockAppConfigurationTrait
{
    /**
     * Check if the configuration has been validated
     *
     * @return bool True if validated, false otherwise
     */
    public function isValidated(): bool
    {
        return $this->isValidated;
    }
}
