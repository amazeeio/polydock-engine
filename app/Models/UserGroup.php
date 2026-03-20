<?php

namespace App\Models;

use App\Enums\UserGroupRoleEnum;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get the users associated with the group.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the app instances associated with the group.
     */
    public function appInstances(): HasMany
    {
        return $this->hasMany(PolydockAppInstance::class);
    }

    public function appInstancesStageCreate(): HasMany
    {
        return $this->appInstances()->whereIn('status', PolydockAppInstance::$stageCreateStatuses);
    }

    public function appInstancesStageDeploy(): HasMany
    {
        return $this->appInstances()->whereIn('status', PolydockAppInstance::$stageDeployStatuses);
    }

    public function appInstancesStageUpgrade(): HasMany
    {
        return $this->appInstances()->whereIn('status', PolydockAppInstance::$stageUpgradeStatuses);
    }

    public function appInstancesStageRemove(): HasMany
    {
        return $this->appInstances()->whereIn('status', PolydockAppInstance::$stageRemoveStatuses);
    }

    public function appInstancesStageRunning(): HasMany
    {
        return $this->appInstances()->whereIn('status', PolydockAppInstance::$stageRunningStatuses);
    }

    public function appInstancesFailed(): HasMany
    {
        return $this->appInstances()->whereIn('status', PolydockAppInstance::$failedStatuses);
    }

    /**
     * Get the group name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($userGroup) {
            if (empty($userGroup->slug)) {
                $slug = Str::slug($userGroup->name);
                $originalSlug = $slug;
                $count = 1;

                while (static::where('slug', $slug)->exists()) {
                    $slug = $originalSlug.'-'.$count;
                    $count++;
                }

                $userGroup->slug = $slug;
            }
        });
    }

    public function getNewAppInstanceForThisApp(PolydockStoreApp $storeApp, ?string $name = null): PolydockAppInstance
    {
        return self::getNewAppInstanceForThisAppForThisGroup($storeApp, $this, $name);
    }

    public static function getNewAppInstanceForThisAppForThisGroup(
        PolydockStoreApp $storeApp,
        UserGroup $userGroup,
        ?string $name = null,
    ): PolydockAppInstance {
        Log::info('Creating unallocated instance', [
            'app_id' => $storeApp->id,
            'app_name' => $storeApp->name,
            'requested_name' => $name,
        ]);

        $allocationLock = Str::uuid()->toString();
        $lockedInstance = null;

        // If no custom name is requested, attempt to grab an unallocated instance
        if ($name === null) {
            // Attempt to lock a single unallocated instance for this store app
            PolydockAppInstance::where('polydock_store_app_id', $storeApp->id)
                ->whereNull('user_group_id')
                ->whereNull('allocation_lock')
                ->where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED)
                ->limit(1)
                ->update(['allocation_lock' => $allocationLock, 'user_group_id' => $userGroup->id]);

            // Check if we got a lock by querying for the instance with our lock
            $lockedInstance = PolydockAppInstance::where('polydock_store_app_id', $storeApp->id)
                ->where('allocation_lock', $allocationLock)
                ->first();
        }

        if ($lockedInstance) {
            Log::info('Grabbed unallocated instance via lock', [
                'app_id' => $storeApp->id,
                'app_name' => $storeApp->name,
                'group_id' => $userGroup->id,
                'group_name' => $userGroup->name,
                'app_instance_id' => $lockedInstance->id,
                'allocation_lock' => $allocationLock,
            ]);

            if ($lockedInstance->remoteRegistration) {
                $lockedInstance->remoteRegistration->setResultValue('message', 'Configuring trial authentication...');
                if ($lockedInstance->getKeyValue('lagoon-generate-app-admin-username')) {
                    $lockedInstance->remoteRegistration->setResultValue(
                        'app_admin_username',
                        $lockedInstance->getKeyValue('lagoon-generate-app-admin-username'),
                    );
                }

                if ($lockedInstance->getKeyValue('lagoon-generate-app-admin-password')) {
                    $lockedInstance->remoteRegistration->setResultValue(
                        'app_admin_password',
                        $lockedInstance->getKeyValue('lagoon-generate-app-admin-password'),
                    );
                }
                $lockedInstance->remoteRegistration->save();
            }

            $lockedInstance
                ->setStatus(PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM)
                ->save();

            return $lockedInstance;
        } else {
            $appInstance = PolydockAppInstance::create([
                'name' => $name,
                'polydock_store_app_id' => $storeApp->id,
                'user_group_id' => $userGroup->id,
                'allocation_lock' => $allocationLock,
                'status' => PolydockAppInstanceStatus::PENDING_PRE_CREATE,
                'config' => [], // Empty config for now
            ]);

            Log::info('Allocated app instance created for group', [
                'app_id' => $storeApp->id,
                'app_name' => $storeApp->name,
                'group_id' => $userGroup->id,
                'group_name' => $userGroup->name,
            ]);

            return $appInstance;
        }
    }
}
