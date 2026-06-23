<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mappings = [
            'App\Polydock\CoreAmazeeioGeneric\PolydockApp' => 'App\Polydock\Apps\Generic\PolydockApp',
            'App\Polydock\CoreAmazeeioGeneric\PolydockAiApp' => 'App\Polydock\Apps\Generic\PolydockAiApp',
            'App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp' => 'App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp',
            'App\Polydock\Apps\PrivateGpt\PolydockPrivateGptApp' => 'App\Polydock\Apps\PrivateGpt\PolydockPrivateGptApp',
            'App\Polydock\Apps\AnythingLlm\PolydockAnythingLLMApp' => 'App\Polydock\Apps\AnythingLlm\PolydockAnythingLLMApp',
            'App\Polydock\Apps\DependencyTrack\PolydockDependencyTrackApp' => 'App\Polydock\Apps\DependencyTrack\PolydockDependencyTrackApp',
        ];

        foreach ($mappings as $oldClass => $newClass) {
            DB::table('polydock_store_apps')
                ->where('polydock_app_class', $oldClass)
                ->update(['polydock_app_class' => $newClass]);
        }
    }

    public function down(): void
    {
        $mappings = [
            'App\Polydock\Apps\Generic\PolydockApp' => 'App\Polydock\CoreAmazeeioGeneric\PolydockApp',
            'App\Polydock\Apps\Generic\PolydockAiApp' => 'App\Polydock\CoreAmazeeioGeneric\PolydockAiApp',
            'App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp' => 'App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp',
            'App\Polydock\Apps\PrivateGpt\PolydockPrivateGptApp' => 'App\Polydock\Apps\PrivateGpt\PolydockPrivateGptApp',
            'App\Polydock\Apps\AnythingLlm\PolydockAnythingLLMApp' => 'App\Polydock\Apps\AnythingLlm\PolydockAnythingLLMApp',
            'App\Polydock\Apps\DependencyTrack\PolydockDependencyTrackApp' => 'App\Polydock\Apps\DependencyTrack\PolydockDependencyTrackApp',
        ];

        foreach ($mappings as $newClass => $oldClass) {
            DB::table('polydock_store_apps')
                ->where('polydock_app_class', $newClass)
                ->update(['polydock_app_class' => $oldClass]);
        }
    }
};
