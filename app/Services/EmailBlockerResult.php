<?php

namespace App\Services;

class EmailBlockerResult
{
    public function __construct(
        private bool $isBlocked,
        private ?string $reason = null
    ) {}

    /**
     * Determine if the email is blocked.
     */
    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    /**
     * Get the reason for the block.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Get the generic, secure user-facing error message.
     */
    public function getErrorMessage(): string
    {
        if (! $this->isBlocked) {
            return '';
        }

        return 'The email address has been blocked.';
    }

    /**
     * Get the detailed error message containing the ban reason.
     */
    public function getDetailedErrorMessage(): string
    {
        if (! $this->isBlocked) {
            return '';
        }

        return "The email address has been blocked: {$this->reason}.";
    }
}
