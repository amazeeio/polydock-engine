<?php

declare(strict_types=1);

namespace App\Polydock\Core\Attributes;

use Attribute;

/**
 * Attribute to indicate that a Polydock App class provides custom form fields
 * for the App Instance configuration form.
 *
 * The class must implement static methods that return arrays of Filament
 * form/infolist components. Field values are automatically stored/retrieved
 * via PolydockVariables with an 'instance_config_' prefix.
 *
 * Use array_merge(parent::getAppInstanceFormSchema(), [...]) in child classes
 * to support field inheritance from parent classes.
 *
 * @example
 * ```php
 * use App\Polydock\Core\Attributes\PolydockAppInstanceFields;
 * use App\Polydock\Core\Contracts\HasAppInstanceFormFields;
 *
 * #[PolydockAppInstanceFields]
 * class MyApp extends PolydockAppBase implements HasAppInstanceFormFields
 * {
 *     public static function getAppInstanceFormSchema(): array
 *     {
 *         return array_merge(parent::getAppInstanceFormSchema(), [
 *             Forms\Components\TextInput::make('my_field')->required(),
 *         ]);
 *     }
 *
 *     public static function getAppInstanceInfolistSchema(): array
 *     {
 *         return array_merge(parent::getAppInstanceInfolistSchema(), [
 *             Infolists\Components\TextEntry::make('my_field'),
 *         ]);
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class PolydockAppInstanceFields
{
    /**
     * Prefix applied to all custom field names to avoid collisions with model fields.
     */
    public const string FIELD_PREFIX = 'instance_config_';

    /**
     * @param  string  $formMethod  Static method name that returns Filament form schema array
     * @param  string  $infolistMethod  Static method name that returns Filament infolist schema array
     */
    public function __construct(
        public readonly string $formMethod = 'getAppInstanceFormSchema',
        public readonly string $infolistMethod = 'getAppInstanceInfolistSchema',
    ) {}
}
