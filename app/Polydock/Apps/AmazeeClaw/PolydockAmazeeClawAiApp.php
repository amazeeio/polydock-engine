<?php

declare(strict_types=1);

namespace App\Polydock\Apps\AmazeeClaw;

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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Override;

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

    #[Override]
    public static function getStoreAppFormSchema(): array
    {
        return [
            TextInput::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('e.g. kimi-k2.5')
                ->maxLength(255)
                ->helperText('Default model for OpenClaw amazee.ai behavior. Used when instance value is not set.'),
            Select::make('amazeeai_key_mode')
                ->label('Amazee AI Key Mode')
                ->options([
                    'manual' => 'Inject keys manually / from request data',
                    'auto' => 'Auto-generate keys via amazee.ai API',
                ])
                ->default('manual')
                ->helperText('Choose whether to auto-generate AI keys using the Amazee AI backend, or inject them manually.'),
        ];
    }

    #[Override]
    public static function getStoreAppInfolistSchema(): array
    {
        return [
            TextEntry::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('Not configured'),
            TextEntry::make('amazeeai_key_mode')
                ->label('Amazee AI Key Mode')
                ->placeholder('Inject keys manually / from request data'),
        ];
    }

    #[Override]
    public static function getAppInstanceFormSchema(): array
    {
        return [
            TextInput::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('e.g. kimi-k2.5')
                ->maxLength(255)
                ->helperText('Optional override for this specific instance.'),
        ];
    }

    #[Override]
    public static function getAppInstanceInfolistSchema(): array
    {
        return [
            TextEntry::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('Not configured'),
        ];
    }
}
