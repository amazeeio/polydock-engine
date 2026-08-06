<?php

use App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp;
use App\Polydock\Apps\AnythingLlm\PolydockAnythingLLMApp;
use App\Polydock\Apps\DependencyTrack\PolydockDependencyTrackApp;
use App\Polydock\Apps\Generic\PolydockAiApp;
use App\Polydock\Apps\Generic\PolydockApp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table): void {
            $table->string('migrated_from_class')->nullable();
        });

        DB::transaction(function (): void {
            $mappings = [
                'App\Polydock\CoreAmazeeioGeneric\PolydockApp' => PolydockApp::class,
                'App\Polydock\CoreAmazeeioGeneric\PolydockAiApp' => PolydockAiApp::class,
                'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp' => PolydockApp::class,
                'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp' => PolydockAiApp::class,
                'Amazeeio\PolydockAppAmazeeclaw\PolydockAmazeeClawAiApp' => PolydockAmazeeClawAiApp::class,
                'Amazeeio\PolydockAppAmazeeioPrivateGpt\PolydockPrivateGptApp' => 'App\Polydock\Apps\PrivateGpt\PolydockPrivateGptApp',
                'Amazeeio\PolydockAppAnythingLLM\PolydockAnythingLLMApp' => PolydockAnythingLLMApp::class,
                'Amazeeio\PolydockAppDependencyTrack\PolydockDependencyTrackApp' => PolydockDependencyTrackApp::class,
            ];

            foreach ($mappings as $oldClass => $newClass) {
                DB::table('polydock_store_apps')
                    ->where('polydock_app_class', $oldClass)
                    ->update([
                        'polydock_app_class' => $newClass,
                        'migrated_from_class' => $oldClass,
                    ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (Schema::hasColumn('polydock_store_apps', 'migrated_from_class')) {
                DB::table('polydock_store_apps')
                    ->whereNotNull('migrated_from_class')
                    ->update([
                        'polydock_app_class' => DB::raw('migrated_from_class'),
                    ]);
            } else {
                $mappings = [
                    PolydockApp::class => 'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp',
                    PolydockAiApp::class => 'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp',
                    PolydockAmazeeClawAiApp::class => 'Amazeeio\PolydockAppAmazeeclaw\PolydockAmazeeClawAiApp',
                    'App\Polydock\Apps\PrivateGpt\PolydockPrivateGptApp' => 'Amazeeio\PolydockAppAmazeeioPrivateGpt\PolydockPrivateGptApp',
                    PolydockAnythingLLMApp::class => 'Amazeeio\PolydockAppAnythingLLM\PolydockAnythingLLMApp',
                    PolydockDependencyTrackApp::class => 'Amazeeio\PolydockAppDependencyTrack\PolydockDependencyTrackApp',
                ];

                foreach ($mappings as $newClass => $oldClass) {
                    DB::table('polydock_store_apps')
                        ->where('polydock_app_class', $newClass)
                        ->update(['polydock_app_class' => $oldClass]);
                }
            }
        });

        if (Schema::hasColumn('polydock_store_apps', 'migrated_from_class')) {
            Schema::table('polydock_store_apps', function (Blueprint $table): void {
                $table->dropColumn('migrated_from_class');
            });
        }
    }
};
