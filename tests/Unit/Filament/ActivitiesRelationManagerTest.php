<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Admin\RelationManagers\ActivitiesRelationManager;
use App\Models\PolydockAppInstance;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Tests\TestCase;

/**
 * Regression test for the activitylog v5 upgrade: the relation manager
 * pointed at `activities`, which no longer exists on models using only
 * the LogsActivity trait, breaking the admin activity tab at runtime.
 */
class ActivitiesRelationManagerTest extends TestCase
{
    public function test_every_model_using_the_relation_manager_has_its_relationship(): void
    {
        $relationship = ActivitiesRelationManager::getRelationshipName();

        // Models whose Filament resource registers ActivitiesRelationManager.
        foreach ([User::class, UserGroup::class, PolydockAppInstance::class] as $model) {
            $instance = new $model;

            $this->assertTrue(
                method_exists($instance, $relationship),
                "{$model} is missing the '{$relationship}' relationship used by ActivitiesRelationManager",
            );

            $this->assertInstanceOf(MorphMany::class, $instance->{$relationship}());
        }
    }
}
