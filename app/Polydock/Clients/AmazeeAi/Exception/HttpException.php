<?php

declare(strict_types=1);

namespace App\Polydock\Clients\AmazeeAi\Exception;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(private readonly int $statusCode, string $message = '', private readonly ?array $response = null)
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }
}
