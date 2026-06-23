<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Generated\Dto;

/**
 * LlmKeysResponse
 *
 * Auto-generated from OpenAPI specification. Do not edit manually.
 *
 * @see https://api.amazee.ai/openapi.json
 */
final readonly class LlmKeysResponse
{
    public function __construct(
        /**
         * Id
         */
        public int $id,
        /**
         * Database Name
         */
        public ?string $database_name = null,
        /**
         * Name
         */
        public ?string $name = null,
        /**
         * Database Host
         */
        public ?string $database_host = null,
        /**
         * Database Username
         */
        public ?string $database_username = null,
        /**
         * Database Password
         */
        public ?string $database_password = null,
        /**
         * Litellm Token
         */
        public ?string $litellm_token = null,
        /**
         * Litellm Api Url
         */
        public ?string $litellm_api_url = null,
        /**
         * Region
         */
        public ?string $region = null,
        /**
         * Created At
         */
        public ?string $created_at = null,
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
