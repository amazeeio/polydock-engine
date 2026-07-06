<?php

declare(strict_types=1);

namespace App\Polydock\Core\Attributes;

use Attribute;

/**
 * Attribute to define a human-readable title for a Polydock App class.
 *
 * When applied to a class implementing PolydockAppInterface, this title
 * will be displayed in admin dropdowns and other UI elements instead of
 * the raw class name.
 *
 * @example
 * ```php
 * use App\Polydock\Core\Attributes\PolydockAppTitle;
 *
 * #[PolydockAppTitle('Generic Lagoon App')]
 * class PolydockApp extends PolydockAppBase
 * {
 *     // ...
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class PolydockAppTitle
{
    /**
     * @param  string  $title  The human-readable title for the app class
     * @param  string|null  $description  Optional description for additional context
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
    ) {}
}
