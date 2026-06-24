<?php

declare(strict_types=1);

namespace App\Polydock\Apps\PrivateGpt\Generated\Dto;

/**
 * TeamResponse
 *
 * Auto-generated from OpenAPI specification. Do not edit manually.
 *
 * @see https://api.amazee.ai/openapi.json
 */
final readonly class TeamResponse
{
    public function __construct(
        /**
         * Name
         */
        public string $name,
        /**
         * Admin Email
         */
        public string $admin_email,
        /**
         * Id
         */
        public int $id,
        /**
         * Is Active
         */
        public bool $is_active,
        /**
         * Is Always Free
         */
        public bool $is_always_free,
        /**
         * Created At
         */
        public string $created_at,
        /**
         * Phone
         */
        public ?string $phone = null,
        /**
         * Billing Address
         */
        public ?string $billing_address = null,
        /**
         * Force User Keys
         */
        public ?bool $force_user_keys = null,
        /**
         * Updated At
         */
        public ?string $updated_at = null,
        /**
         * Last Payment
         */
        public ?string $last_payment = null,
        /**
         * Deleted At
         */
        public ?string $deleted_at = null,
        /**
         * Retention Warning Sent At
         */
        public ?string $retention_warning_sent_at = null
    ) {}
}
