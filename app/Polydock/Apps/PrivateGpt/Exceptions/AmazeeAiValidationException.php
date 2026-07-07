<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Exceptions;

use CuyZ\Valinor\Mapper\MappingError;
use Throwable;

class AmazeeAiValidationException extends AmazeeAiClientException
{
    public function __construct(string $message, MappingError $mappingError, int $code = 0, ?Throwable $previous = null)
    {
        $detailedMessage = $message.': '.$this->formatMappingError($mappingError);
        parent::__construct($detailedMessage, $code, $previous);
    }

    private function formatMappingError(MappingError $error): string
    {
        $messages = [];
        foreach ($error->messages() as $message) {
            $messages[] = (string) $message;
        }

        return implode('; ', $messages);
    }
}
