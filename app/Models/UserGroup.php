<?php

namespace App\Models;

use App\Enums\UserGroupRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'friendly_name',
        'slug',
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
}
