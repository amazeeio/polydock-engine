<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Interfaces;

use App\Polydock\Core\PolydockAppBase;

interface LoggerInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): PolydockAppBase;

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): PolydockAppBase;

    /**
     * @return array<string, mixed>
     */
    public function getLogContext(string $location): array;
}
