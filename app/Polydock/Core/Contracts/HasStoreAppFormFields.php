<?php

declare(strict_types=1);

namespace App\Polydock\Core\Contracts;

use Filament\Schemas\Components\Component;

/**
 * Interface for Polydock App classes that provide custom Store App form fields.
 *
 * Implement this interface alongside the #[PolydockAppStoreFields] attribute
 * for type safety and IDE autocompletion.
 *
 * Field names should NOT include the 'app_config_' prefix - it is added automatically
 * when the schema is processed.
 */
interface HasStoreAppFormFields
{
    /**
     * Get the Filament form schema for Store App configuration.
     *
     * Field names should use snake_case (without 'app_config_' prefix).
     * Use the 'encrypted' extraAttribute to mark sensitive fields for encrypted storage.
     *
     * @return array<Component>
     *
     * @example
     * ```php
     * public static function getStoreAppFormSchema(): array
     * {
     *     return [
     *         Forms\Components\Section::make('AI Settings')
     *             ->schema([
     *                 Forms\Components\TextInput::make('ai_endpoint')
     *                     ->label('AI Endpoint URL')
     *                     ->url()
     *                     ->required(),
     *                 Forms\Components\TextInput::make('ai_api_key')
     *                     ->label('API Key')
     *                     ->password()
     *                     ->extraAttributes(['encrypted' => true]),
     *             ]),
     *     ];
     * }
     * ```
     */
    public static function getStoreAppFormSchema(): array;

    /**
     * Get the Filament infolist schema for displaying Store App configuration.
     *
     * Field names should match those in getStoreAppFormSchema() (without prefix).
     * The prefix is added automatically when the schema is processed.
     *
     * @return array<Component>
     *
     * @example
     * ```php
     * public static function getStoreAppInfolistSchema(): array
     * {
     *     return [
     *         Infolists\Components\Section::make('AI Settings')
     *             ->schema([
     *                 Infolists\Components\TextEntry::make('ai_endpoint')
     *                     ->label('AI Endpoint URL'),
     *                 Infolists\Components\TextEntry::make('ai_api_key')
     *                     ->label('API Key')
     *                     ->formatStateUsing(fn () => '••••••••'), // Hide sensitive values
     *             ]),
     *     ];
     * }
     * ```
     */
    public static function getStoreAppInfolistSchema(): array;
}
