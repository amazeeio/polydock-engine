<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polydock_store_apps', function (Blueprint $table) {
            $table->string('migrated_from_class')->nullable();
        });

        DB::transaction(function () {
            $mappings = [
                'App\Polydock\CoreAmazeeioGeneric\PolydockApp' => 'App\Polydock\Apps\Generic\PolydockApp',
                'App\Polydock\CoreAmazeeioGeneric\PolydockAiApp' => 'App\Polydock\Apps\Generic\PolydockAiApp',
                'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp' => 'App\Polydock\Apps\Generic\PolydockApp',
                'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp' => 'App\Polydock\Apps\Generic\PolydockAiApp',
                'Amazeeio\PolydockAppAmazeeclaw\PolydockAmazeeClawAiApp' => 'App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp',
                'Amazeeio\PolydockAppAmazeeioPrivateGpt\PolydockPrivateGptApp' => 'App\Polydock\Apps\PrivateGpt\PolydockPrivateGptApp',
                'Amazeeio\PolydockAppAnythingLLM\PolydockAnythingLLMApp' => 'App\Polydock\Apps\AnythingLlm\PolydockAnythingLLMApp',
                'Amazeeio\PolydockAppDependencyTrack\PolydockDependencyTrackApp' => 'App\Polydock\Apps\DependencyTrack\PolydockDependencyTrackApp',
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
        DB::transaction(function () {
            if (Schema::hasColumn('polydock_store_apps', 'migrated_from_class')) {
                DB::table('polydock_store_apps')
                    ->whereNotNull('migrated_from_class')
                    ->update([
                        'polydock_app_class' => DB::raw('migrated_from_class'),
                    ]);
            } else {
                $mappings = [
                    'App\Polydock\Apps\Generic\PolydockApp' => 'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp',
                    'App\Polydock\Apps\Generic\PolydockAiApp' => 'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp',
                    'App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp' => 'Amazeeio\PolydockAppAmazeeclaw\PolydockAmazeeClawAiApp',
                    'App\Polydock\Apps\PrivateGpt\PolydockPrivateGptApp' => 'Amazeeio\PolydockAppAmazeeioPrivateGpt\PolydockPrivateGptApp',
                    'App\Polydock\Apps\AnythingLlm\PolydockAnythingLLMApp' => 'Amazeeio\PolydockAppAnythingLLM\PolydockAnythingLLMApp',
                    'App\Polydock\Apps\DependencyTrack\PolydockDependencyTrackApp' => 'Amazeeio\PolydockAppDependencyTrack\PolydockDependencyTrackApp',
                ];

                foreach ($mappings as $newClass => $oldClass) {
                    DB::table('polydock_store_apps')
                        ->where('polydock_app_class', $newClass)
                        ->update(['polydock_app_class' => $oldClass]);
                }
            }
        });

        if (Schema::hasColumn('polydock_store_apps', 'migrated_from_class')) {
            Schema::table('polydock_store_apps', function (Blueprint $table) {
                $table->dropColumn('migrated_from_class');
            });
        }
    }
};
