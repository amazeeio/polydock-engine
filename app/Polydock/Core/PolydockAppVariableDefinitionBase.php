<?php

namespace App\Polydock\Core;

class PolydockAppVariableDefinitionBase implements PolydockAppVariableDefinitionInterface
{
    public function __construct(protected string $name) {}

    /**
     * Get the name of the app variable definition
     *
     * @return string The name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the name of the app variable definition
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
