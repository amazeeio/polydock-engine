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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
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
        'first_name',
        'last_name',
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
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'name',
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
     * Get the user's full name.
     *
     * @return string
     */
    public function getNameAttribute(): string
    {
        return Str::trim("{$this->first_name} {$this->last_name}");
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

    /**
     * Get all tenants the user can access
     * 
     * @param \Filament\Panel $panel
     * @return \Illuminate\Support\Collection
     */ 
    public function getTenants(Panel $panel): Collection
    {
        return $this->groups;
    }
 
    /**
     * Check if the user can access the tenant
     * 
     * @param \Illuminate\Database\Eloquent\Model $tenant
     * @return bool
     */ 
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->groups()->whereKey($tenant)->exists();
    }

    /**
     * Check if the user can access the panel
     * 
     * @param \Filament\Panel $panel
     * @return bool
     */ 
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get all remote registration requests for this user
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function remoteRegistrations(): HasMany
    {
        return $this->hasMany(UserRemoteRegistration::class);
    }
}
