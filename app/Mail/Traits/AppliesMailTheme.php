<?php

declare(strict_types=1);

namespace App\Mail\Traits;

use Illuminate\Support\Facades\Config;

trait AppliesMailTheme
{
    /**
     * Get the mjml config, with the store app's mail theme applied when one
     * is configured and defined in mail.mjml-config.themes.
     */
    protected function mjmlConfig(): array
    {
        $config = Config::get('mail.mjml-config');

        $theme = $this->appInstance->storeApp->mail_theme;

        if ($theme && isset($config['themes'][$theme])) {
            $config['default_theme'] = $theme;
        }

        return $config;
    }
}
