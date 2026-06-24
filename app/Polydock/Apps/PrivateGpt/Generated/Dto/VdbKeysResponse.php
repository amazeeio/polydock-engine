<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Generated\Dto;

/**
 * VdbKeysResponse
 *
 * Auto-generated from OpenAPI specification. Do not edit manually.
 *
 * @see https://api.amazee.ai/openapi.json
 */
final readonly class VdbKeysResponse
{
    public function __construct(
        /**
         * Id
         */
        public int $id,
        /**
         * Litellm Token
         */
        public string $litellm_token,
        /**
         * Litellm Api Url
         */
        public string $litellm_api_url,
        /**
         * Region
         */
        public string $region,
        /**
         * Name
         */
        public string $name,
        /**
         * Owner Id
         */
        public ?int $owner_id = null,
        /**
         * Team Id
         */
        public ?int $team_id = null
    ) {}
}
