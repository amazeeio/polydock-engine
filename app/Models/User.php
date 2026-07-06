<?php

namespace App\Models;

use App\Enums\UserGroupRoleEnum;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Jeffgreco13\FilamentBreezy\Models\BreezySession;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string|null $okta_sub
 * @property-read BreezySession|null $breezySession
 * @property-read array<int, string>|null $two_factor_recovery_codes
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * Explicitly set the guard for spatie/laravel-permission.
     * Without this, Sanctum API requests resolve as 'sanctum' guard
     * which causes guard mismatch errors with roles created for 'web'.
     */
    protected string $guard_name = 'web';

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
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
     * @return BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_user_group', 'user_id', 'user_group_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get all primary groups this user belongs to
     *
     * @return BelongsToMany
     */
    public function primaryGroups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_user_group', 'user_id', 'user_group_id')
            ->wherePivot('role', UserGroupRoleEnum::OWNER->value);
    }

    /**
     * Get all admin groups this user belongs to.
     */
    public function adminGroups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_user_group', 'user_id', 'user_group_id')
            ->wherePivot('role', UserGroupRoleEnum::ADMIN->value);
    }

    /**
     * Get all member groups this user belongs to
     *
     * @return BelongsToMany
     */
    public function memberGroups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_user_group', 'user_id', 'user_group_id')
            ->wherePivot('role', UserGroupRoleEnum::MEMBER->value);
    }

    /**
     * Get all viewer groups this user belongs to
     *
     * @return BelongsToMany
     */
    public function viewerGroups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_user_group', 'user_id', 'user_group_id')
            ->wherePivot('role', UserGroupRoleEnum::VIEWER->value);
    }

    public function groupRole(UserGroup $group): ?UserGroupRoleEnum
    {
        $role = $this->groups()
            ->whereKey($group->getKey())
            ->first()
            ?->pivot
            ?->role;

        return is_string($role) && $role !== '' ? UserGroupRoleEnum::tryFrom($role) : null;
    }

    public function hasGroupRoleAtLeast(UserGroup $group, UserGroupRoleEnum $required): bool
    {
        $role = $this->groupRole($group);

        return $role !== null && $role->atLeast($required);
    }

    /**
     * Check if the user can access the panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('super_admin') || $this->can('access_admin_panel');
    }

    /**
     * Get all remote registration requests for this user
     */
    public function remoteRegistrations(): HasMany
    {
        return $this->hasMany(UserRemoteRegistration::class);
    }
}
