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
