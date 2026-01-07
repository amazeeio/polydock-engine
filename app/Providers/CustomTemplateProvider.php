<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class CustomTemplateProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $templatePath = config('mail.custom_templates_path', storage_path('app/private/templates'));
        $themes = [];
        
        // Only register custom template path if directory exists
        if (is_dir($templatePath)) {
            $themes = $this->discoverThemes($templatePath);
            
            foreach ($themes as $themeName => $themePath) {
                $this->loadViewsFrom($themePath, $themeName);
                Log::info('Custom theme registered', [
                    'theme' => $themeName,
                    'path' => $themePath,
                ]);
            }
            
            Log::info('Custom email themes loaded', [
                'path' => $templatePath,
                'themes_found' => count($themes),
                'theme_names' => array_keys($themes)
            ]);
        } else {
            Log::debug('Custom templates directory not found', ['path' => $templatePath]);
        }
        
        // Make themes available globally via service container
        $this->app->singleton('mail.themes', fn() => array_keys($themes));
    }

    /**
     * Discover theme directories and return them as an array.
     * 
     * @param string $templatePath
     * @return array<string, string> Array of theme name => path
     */
    private function discoverThemes(string $templatePath): array
    {
        $themes = [];
        
        $directories = array_filter(
            scandir($templatePath),
            fn($item) => is_dir($templatePath . DIRECTORY_SEPARATOR . $item) && !str_starts_with($item, '.')
        );
        
        foreach ($directories as $dir) {
            $themes[$dir] = $templatePath . DIRECTORY_SEPARATOR . $dir;
        }
        
        return $themes;
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
