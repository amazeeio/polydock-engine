<?php

declare(strict_types=1);

namespace App\Polydock\Core\Contracts;

use Filament\Forms\Components\Component;

/**
 * Interface for Polydock App classes that provide custom App Instance form fields.
 *
 * Implement this interface alongside the #[PolydockAppInstanceFields] attribute
 * for type safety and IDE autocompletion.
 *
 * Field names should NOT include the 'instance_config_' prefix - it is added automatically
 * when the schema is processed.
 *
 * Use array_merge(parent::getAppInstanceFormSchema(), [...]) in child classes
 * to support field inheritance from parent classes.
 */
interface HasAppInstanceFormFields
{
    /**
     * Get the Filament form schema for App Instance configuration.
     *
     * Field names should use snake_case (without 'instance_config_' prefix).
     * Use the 'encrypted' extraAttribute to mark sensitive fields for encrypted storage.
     * Use array_merge(parent::getAppInstanceFormSchema(), [...]) for inheritance.
     *
     * @return array<Component>
     *
     * @example
     * ```php
     * public static function getAppInstanceFormSchema(): array
     * {
     *     return array_merge(parent::getAppInstanceFormSchema(), [
     *         Forms\Components\Section::make('Instance Settings')
     *             ->schema([
     *                 Forms\Components\TextInput::make('custom_domain')
     *                     ->label('Custom Domain')
     *                     ->url(),
     *                 Forms\Components\TextInput::make('api_key')
     *                     ->label('API Key')
     *                     ->password()
     *                     ->extraAttributes(['encrypted' => true]),
     *             ]),
     *     ]);
     * }
     * ```
     */
    public static function getAppInstanceFormSchema(): array;

    /**
     * Get the Filament infolist schema for displaying App Instance configuration.
     *
     * Field names should match those in getAppInstanceFormSchema() (without prefix).
     * The prefix is added automatically when the schema is processed.
     * Use array_merge(parent::getAppInstanceInfolistSchema(), [...]) for inheritance.
     *
     * @return array<\Filament\Infolists\Components\Component>
     *
     * @example
     * ```php
     * public static function getAppInstanceInfolistSchema(): array
     * {
     *     return array_merge(parent::getAppInstanceInfolistSchema(), [
     *         Infolists\Components\Section::make('Instance Settings')
     *             ->schema([
     *                 Infolists\Components\TextEntry::make('custom_domain')
     *                     ->label('Custom Domain'),
     *                 Infolists\Components\TextEntry::make('api_key')
     *                     ->label('API Key')
     *                     ->formatStateUsing(fn ($state) => $state ? '••••••••' : 'Not set'),
     *             ]),
     *     ]);
     * }
     * ```
     */
    public static function getAppInstanceInfolistSchema(): array;
}
