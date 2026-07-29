<?php

namespace App\Rules;

use App\Services\EmailBlockerService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class BannedEmail implements ValidationRule
{
    public function __construct(
        private bool $detailed = false
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value) || ! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $result = app(EmailBlockerService::class)->checkEmail($value);

        if ($result->isBlocked()) {
            if ($this->detailed) {
                $fail($result->getDetailedErrorMessage());
            } else {
                $fail($result->getErrorMessage());
            }
        }
    }
}
