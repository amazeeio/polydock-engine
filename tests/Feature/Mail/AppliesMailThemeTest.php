<?php

namespace Tests\Feature\Mail;

use App\Mail\AppInstanceReadyMail;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AppliesMailThemeTest extends TestCase
{
    private function mailConfigForTheme(?string $mailTheme): array
    {
        $instance = new PolydockAppInstance;
        $instance->setRelation('storeApp', new PolydockStoreApp(['mail_theme' => $mailTheme]));

        $mail = new AppInstanceReadyMail($instance, new User);

        return $mail->content()->with['config'];
    }

    public function test_store_app_mail_theme_overrides_default_theme(): void
    {
        Config::set('mail.mjml-config.themes.branded', ['name' => 'Branded']);

        $this->assertSame('branded', $this->mailConfigForTheme('branded')['default_theme']);
    }

    public function test_unknown_or_missing_theme_falls_back_to_default(): void
    {
        $default = config('mail.mjml-config.default_theme');

        $this->assertSame($default, $this->mailConfigForTheme('nonexistent')['default_theme']);
        $this->assertSame($default, $this->mailConfigForTheme(null)['default_theme']);
    }
}
