<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Generated\Dto;

/**
 * APIToken
 *
 * Auto-generated from OpenAPI specification. Do not edit manually.
 *
 * @see https://api.amazee.ai/openapi.json
 */
final readonly class APIToken
{
    public function __construct(
        /**
         * Name
         */
        public string $name,
        /**
         * Id
         */
        public int $id,
        /**
         * Token
         */
        public string $token,
        /**
         * Created At
         */
        public string $created_at,
        /**
         * User Id
         */
        public int $user_id,
        /**
         * Last Used At
         */
        public ?string $last_used_at = null
    ) {}
}
