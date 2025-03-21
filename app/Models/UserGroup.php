<?php

namespace App\Models;

use App\Enums\UserGroupRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGroup extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
    ];

    /**
     * Get all users in this group
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Get all users with 'owner' role in this group
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function owners()
    {
        return $this->belongsToMany(User::class, 'user_user_group')
            ->wherePivot('role', UserGroupRoleEnum::OWNER->value);
    }

    /**
     * Get all users with 'member' role in this group
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'user_user_group')
            ->wherePivot('role', UserGroupRoleEnum::MEMBER->value);
    }

    /**
     * Get all users with 'viewer' role in this group
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function viewers()
    {
        return $this->belongsToMany(User::class, 'user_user_group')
            ->wherePivot('role', UserGroupRoleEnum::VIEWER->value);
    }

    /**
     * Get all app instances for this group
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function appInstances(): HasMany
    {
        return $this->hasMany(PolydockAppInstance::class);
    }

    /**
     * Get pending app instances
     */
    public function appInstancesPending(): HasMany
    {
        return $this->appInstances()
            ->whereIn('status', PolydockAppInstance::$pendingStatuses);
    }

    /**
     * Get completed app instances
     */
    public function appInstancesCompleted(): HasMany
    {
        return $this->appInstances()
            ->whereIn('status', PolydockAppInstance::$completedStatuses);
    }

    /**
     * Get failed app instances
     */
    public function appInstancesFailed(): HasMany
    {
        return $this->appInstances()
            ->whereIn('status', PolydockAppInstance::$failedStatuses);
    }

    /**
     * Get polling app instances
     */
    public function appInstancesPolling(): HasMany
    {
        return $this->appInstances()
            ->whereIn('status', PolydockAppInstance::$pollingStatuses);
    }

    /**
     * Get create stage app instances
     */
    public function appInstancesStageCreate(): HasMany
    {
        return $this->appInstances()
            ->whereIn('status', PolydockAppInstance::$stageCreateStatuses);
    }

    /**
     * Get deploy stage app instances
     */
    public function appInstancesStageDeploy(): HasMany
    {
        return $this->appInstances()
            ->whereIn('status', PolydockAppInstance::$stageDeployStatuses);
    }

    /**
     * Get remove stage app instances
     */
    public function appInstancesStageRemove(): HasMany
    {
        return $this->appInstances()
            ->whereIn('status', PolydockAppInstance::$stageRemoveStatuses);
    }

    /**
     * Get upgrade stage app instances
     */
    public function appInstancesStageUpgrade(): HasMany
    {
        return $this->appInstances()
            ->whereIn('status', PolydockAppInstance::$stageUpgradeStatuses);
    }

    /**
     * Get running stage app instances
     */
    public function appInstancesStageRunning(): HasMany
    {
        return $this->appInstances()
            ->whereIn('status', PolydockAppInstance::$stageRunningStatuses);
    }

    /**
     * Boot the model
     */
    protected static function booted()
    {
        static::saving(function ($userGroup) {
            $userGroup->name = preg_replace('/\s+/', ' ', trim($userGroup->name));
        });
        
        static::creating(function ($userGroup) {
            if (! $userGroup->slug) {
                $slug = Str::slug($userGroup->name);
                $count = 1;

                while (static::where('slug', $slug)->exists()) {
                    $slug = Str::slug($userGroup->name) . '-' . $count++;
                }

                $userGroup->slug = $slug;
            }
        });
    }
}
