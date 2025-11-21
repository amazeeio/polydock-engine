<?php

namespace App\Mail\Traits;

use Illuminate\Support\Facades\View;

trait ResolvesThemeTemplate
{
    /**
     * Resolve the theme and markdown template paths.
     * Sets $this->theme and $this->markdownTemplate accordingly.
     * 
     * @param string $themeBase The theme namespace (e.g., 'promet')
     * @param string $markdownTemplate The default markdown template path
     * @throws \Exception
     */
    protected function resolveThemeTemplate(string|null $themeBase, string $markdownTemplate): void
    {
        if (empty($themeBase)) {
            return;
        }

        $this->theme = sprintf("%s::%s", $themeBase, "emails.theme");
        
        $themedTemplate = sprintf("%s::%s", $themeBase, $markdownTemplate);
        
        if (View::exists($themedTemplate)) {
            $this->markdownTemplate = $themedTemplate;
        } else {
            if (!View::exists($markdownTemplate)) {
                throw new \Exception("Unable to find any template corresponding to " . $markdownTemplate);
            }
        }
    }
}
