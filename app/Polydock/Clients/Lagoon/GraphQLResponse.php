<?php

declare(strict_types=1);

namespace App\Polydock\Clients\Lagoon;

class GraphQLResponse
{
    public function __construct(private readonly array $data, private readonly array $errors = []) {}

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
