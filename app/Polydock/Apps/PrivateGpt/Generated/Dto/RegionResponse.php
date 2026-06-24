<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Generated\Dto;

/**
 * RegionResponse
 *
 * Auto-generated from OpenAPI specification. Do not edit manually.
 *
 * @see https://api.amazee.ai/openapi.json
 */
final readonly class RegionResponse
{
    public function __construct(
        /**
         * Name
         */
        public string $name,
        /**
         * Postgres Host
         */
        public string $postgres_host,
        /**
         * Postgres Admin User
         */
        public string $postgres_admin_user,
        /**
         * Postgres Admin Password
         */
        public string $postgres_admin_password,
        /**
         * Litellm Api Url
         */
        public string $litellm_api_url,
        /**
         * Litellm Api Key
         */
        public string $litellm_api_key,
        /**
         * Id
         */
        public int $id,
        /**
         * Created At
         */
        public string $created_at,
        /**
         * Postgres Port
         */
        public ?int $postgres_port = null,
        /**
         * Is Active
         */
        public ?bool $is_active = null,
        /**
         * Is Dedicated
         */
        public ?bool $is_dedicated = null
    ) {}
}
