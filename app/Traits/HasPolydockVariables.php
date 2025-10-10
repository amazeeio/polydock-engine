<?php

namespace App\Traits;

trait HasPolydockVariables
{
    /**
     * Set a variable value
     */
    public function setPolydockVariableValue(string $name, ?string $value, bool $storedEncrypted = false): self
    {
        $variable = $this->variables()->updateOrCreate(
            ['name' => $name],
            []
        );

        $variable->setEncryptedValue($value, $storedEncrypted)->save();

        return $this;
    }

    /**
     * Get a variable value
     */
    public function getPolydockVariableValue(string $name): ?string
    {
        $variable = $this->variables()
            ->where('name', $name)
            ->first();

        return $variable ? $variable->getDecryptedValue() : null;
    }

    /**
     * Check if a variable is stored encrypted
     */
    public function isPolydockVariableEncrypted(string $name): bool
    {
        return (bool) $this->variables()
            ->where('name', $name)
            ->value('is_encrypted');
    }

    /**
     * Remove a variable
     */
    public function removePolydockVariableValue(string $name): self
    {
        $this->variables()
            ->where('name', $name)
            ->delete();

        return $this;
    }
}
