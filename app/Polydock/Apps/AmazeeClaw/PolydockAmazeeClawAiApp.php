<?php

declare(strict_types=1);

namespace App\Polydock\Apps\AmazeeClaw;

use App\Polydock\Apps\AmazeeClaw\Enums\AmazeeAiKeyMode;
use App\Polydock\Apps\AmazeeClaw\Traits\Claim\ClaimAppInstanceTrait;
use App\Polydock\Apps\AmazeeClaw\Traits\Create\PostCreateAppInstanceTrait;
use App\Polydock\Apps\AmazeeClaw\Traits\Create\PreCreateAppInstanceTrait;
use App\Polydock\Apps\AmazeeClaw\Traits\UsesManualAmazeeAiCredentials;
use App\Polydock\Apps\Generic\PolydockAiApp as GenericPolydockAiApp;
use App\Polydock\Core\Attributes\PolydockAppInstanceFields;
use App\Polydock\Core\Attributes\PolydockAppStoreFields;
use App\Polydock\Core\Attributes\PolydockAppTitle;
use App\Polydock\Core\Contracts\HasAppInstanceFormFields;
use App\Polydock\Core\Contracts\HasStoreAppFormFields;
use Filament\Forms;
use Filament\Infolists;

#[PolydockAppTitle('AmazeeClaw AI App')]
#[PolydockAppStoreFields]
#[PolydockAppInstanceFields]
class PolydockAmazeeClawAiApp extends GenericPolydockAiApp implements HasAppInstanceFormFields, HasStoreAppFormFields
{
    use ClaimAppInstanceTrait;
    use PostCreateAppInstanceTrait;
    use PreCreateAppInstanceTrait;
    use UsesManualAmazeeAiCredentials;

    public static string $version = '0.1.10';

    /**
     * Fallback themed word lists for project-name variants, used when the
     * store app has no word lists configured (see Project Naming settings).
     *
     * @return array<int, string>
     */
    #[\Override]
    public static function defaultProjectNamingAdjectives(): array
    {
        return [
            'snappy', 'pinchy', 'crabby', 'clawesome', 'nippy',
            'cheeky', 'zesty', 'scrappy', 'wiggly', 'spiky',
            'grumpy', 'sassy', 'bouncy', 'sneaky', 'jolly',
        ];
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    public static function defaultProjectNamingNouns(): array
    {
        return [
            'crab', 'lobster', 'crayfish', 'prawn', 'shrimp',
            'hermitcrab', 'fiddlercrab', 'kingcrab', 'rocklobster', 'langoustine',
            'scorpion', 'mantis',
        ];
    }

    #[\Override]
    public static function getStoreAppFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('e.g. kimi-k2.5')
                ->maxLength(255)
                ->helperText('Default model for OpenClaw amazee.ai behavior. Used when instance value is not set.'),
            Forms\Components\Select::make('amazeeai_key_mode')
                ->label('Amazee AI Key Mode')
                ->options([
                    AmazeeAiKeyMode::Injected->value => 'Injected — supplied in request data / secret (e.g. MOAD)',
                    AmazeeAiKeyMode::Anonymous->value => 'Anonymous — auto-generate keys per project via amazee.ai',
                    AmazeeAiKeyMode::User->value => 'User — auto-generate keys for the claiming user via amazee.ai',
                ])
                ->default(AmazeeAiKeyMode::Injected->value)
                ->helperText('How AI keys are provided to this app: injected externally, auto-generated per project (anonymous), or auto-generated for the claiming user.'),
        ];
    }

    #[\Override]
    public static function getStoreAppInfolistSchema(): array
    {
        return [
            Infolists\Components\TextEntry::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('Not configured'),
            Infolists\Components\TextEntry::make('amazeeai_key_mode')
                ->label('Amazee AI Key Mode')
                ->placeholder('Injected — supplied in request data / secret'),
        ];
    }

    #[\Override]
    public static function getAppInstanceFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('e.g. kimi-k2.5')
                ->maxLength(255)
                ->helperText('Optional override for this specific instance.'),
        ];
    }

    #[\Override]
    public static function getAppInstanceInfolistSchema(): array
    {
        return [
            Infolists\Components\TextEntry::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('Not configured'),
        ];
    }
}
