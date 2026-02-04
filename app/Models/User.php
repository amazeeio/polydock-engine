<?php

namespace App\Models;

use App\Enums\UserGroupRoleEnum;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use Notifiable;

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
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->groups;
    }

    /**
     * Check if the user can access the tenant
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->groups()->whereKey($tenant)->exists();
    }

    /**
     * Check if the user can access the panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get all remote registration requests for this user
     */
    public function remoteRegistrations(): HasMany
    {
        return $this->hasMany(UserRemoteRegistration::class);
    }
}
