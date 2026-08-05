<?php

declare(strict_types=1);

namespace App\Polydock\Clients\Lagoon;

class GraphQLResponse
{
    /**
     * @param  list<string>  $errors
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data, private readonly array $errors = []) {}

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
