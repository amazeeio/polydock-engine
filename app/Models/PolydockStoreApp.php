<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\PolydockStoreAppStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolydockStoreApp extends Model
{
    use HasFactory;

    protected $fillable = [
        'polydock_store_id',
        'class',
        'name',
        'description',
        'author',
        'website',
        'support_email',
        'lagoon_deploy_git',
        'lagoon_deploy_branch',
        'status',
        'uuid',
        'available_for_trials',
    ];

    protected $casts = [
        'status' => PolydockStoreAppStatusEnum::class,
        'available_for_trials' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'lagoon_deploy_region_id',
        'lagoon_deploy_project_prefix',
        'lagoon_deploy_organization_id',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(PolydockStore::class, 'polydock_store_id');
    }

    /**
     * Get all instances of this store app
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function instances(): HasMany
    {
        return $this->hasMany(PolydockAppInstance::class);
    }

    /**
     * Get the Lagoon deploy region ID attribute
     */
    public function getLagoonDeployRegionIdAttribute(): string
    {
        return $this->store->lagoon_deploy_region_id;
    }

    /**
     * Get the Lagoon project prefix attribute
     */
    public function getLagoonDeployProjectPrefixAttribute(): string
    {
        return $this->store->lagoon_deploy_project_prefix;
    }

    /**
     * Get the Lagoon deploy organization ID attribute
     */
    public function getLagoonDeployOrganizationIdAttribute(): string
    {
        return $this->store->lagoon_deploy_organization_id;
    }
} 