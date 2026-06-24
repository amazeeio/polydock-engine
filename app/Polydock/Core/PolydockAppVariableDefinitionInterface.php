<?php

declare(strict_types=1);

namespace App\Polydock\Core;

interface PolydockAppVariableDefinitionInterface
{
    public function __construct(string $name);

    /**
     * Get the name of the variable
     *
     * @return string The name of the variable
     */
    public function getName(): string;

    /**
     * Set the name of the variable
     *
     * @param  string  $name  The name of the variable
     */
    public function setName(string $name): self;
}
