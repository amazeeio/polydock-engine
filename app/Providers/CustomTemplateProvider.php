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
        
        // Only register custom template path if directory exists
        if (is_dir($templatePath)) {
            $this->loadViewsFrom($templatePath, 'custom');
            
            Log::info('Custom email templates loaded', [
                'path' => $templatePath,
                'templates_found' => count(glob($templatePath . '/*.blade.php'))
            ]);
        } else {
            Log::debug('Custom templates directory not found', ['path' => $templatePath]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
