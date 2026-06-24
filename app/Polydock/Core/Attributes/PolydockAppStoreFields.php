<?php

declare(strict_types=1);

namespace App\Polydock\Core\Attributes;

use Attribute;

/**
 * Attribute to indicate that a Polydock App class provides custom form fields
 * for the Store App configuration form.
 *
 * The class must implement static methods that return arrays of Filament
 * form/infolist components. Field values are automatically stored/retrieved
 * via PolydockVariables with an 'app_config_' prefix.
 *
 * @example
 * ```php
 * use App\Polydock\Core\Attributes\PolydockAppStoreFields;
 * use App\Polydock\Core\Contracts\HasStoreAppFormFields;
 *
 * #[PolydockAppStoreFields]
 * class MyApp extends PolydockAppBase implements HasStoreAppFormFields
 * {
 *     public static function getStoreAppFormSchema(): array
 *     {
 *         return [
 *             Forms\Components\TextInput::make('my_field')->required(),
 *         ];
 *     }
 *
 *     public static function getStoreAppInfolistSchema(): array
 *     {
 *         return [
 *             Infolists\Components\TextEntry::make('my_field'),
 *         ];
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class PolydockAppStoreFields
{
    /**
     * Prefix applied to all custom field names to avoid collisions with model fields.
     */
    public const string FIELD_PREFIX = 'app_config_';

    /**
     * @param  string  $formMethod  Static method name that returns Filament form schema array
     * @param  string  $infolistMethod  Static method name that returns Filament infolist schema array
     */
    public function __construct(
        public readonly string $formMethod = 'getStoreAppFormSchema',
        public readonly string $infolistMethod = 'getStoreAppInfolistSchema',
    ) {}
}
