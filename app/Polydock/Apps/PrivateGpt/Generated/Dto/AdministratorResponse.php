<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Generated\Dto;

/**
 * AdministratorResponse
 *
 * Auto-generated from OpenAPI specification. Do not edit manually.
 *
 * @see https://api.amazee.ai/openapi.json
 */
final readonly class AdministratorResponse
{
    public function __construct(
        /**
         * Email
         */
        public string $email,
        /**
         * Id
         */
        public int $id,
        /**
         * Is Active
         */
        public bool $is_active,
        /**
         * Is Admin
         */
        public bool $is_admin,
        /**
         * Team Id
         */
        public ?int $team_id = null,
        /**
         * Team Name
         */
        public ?string $team_name = null,
        /**
         * Role
         */
        public ?string $role = null
    ) {}
}
