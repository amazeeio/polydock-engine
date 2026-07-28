<?php

namespace App\Forms;

/**
 * Human-readable label for a hosted form class, shown as the "Form Type"
 * option in the admin panel. Classes without it fall back to a headline
 * of the class name.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class FormLabel
{
    public function __construct(public string $label) {}
}
