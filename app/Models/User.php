<?php

namespace App\Models;

use App\Enums\UserGroupRoleEnum;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get all user groups this user belongs to
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(UserGroup::class);
    }

    /**
     * Get all primary groups this user belongs to
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function primaryGroups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_user_group', 'user_id', 'user_group_id')
            ->wherePivot('role', UserGroupRoleEnum::OWNER);
    }

    /**
     * Get all member groups this user belongs to
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function memberGroups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_user_group', 'user_id', 'user_group_id')
            ->wherePivot('role', UserGroupRoleEnum::MEMBER);
    }   

    /**
     * Get all viewer groups this user belongs to
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function viewerGroups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_user_group', 'user_id', 'user_group_id')
            ->wherePivot('role', UserGroupRoleEnum::VIEWER);
    }   

    public function getTenants(Panel $panel): Collection
    {
        return $this->groups;
    }
 
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->groups()->whereKey($tenant)->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
