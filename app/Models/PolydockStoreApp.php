<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\PolydockStoreAppStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Traits\HasPolydockVariables;

class PolydockStoreApp extends Model
{
    use HasFactory, HasPolydockVariables;

    protected $fillable = [
        'polydock_store_id',
        'polydock_app_class',
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
        'lagoon_deploy_region_id_ext',
        'lagoon_deploy_project_prefix',
        'lagoon_deploy_organization_id_ext',
        'lagoon_deploy_private_key',
        'amazee_ai_backend_region_id_ext',
        'unallocated_instances_count',
        'needs_more_unallocated_instances',
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
    public function getLagoonDeployRegionIdExtAttribute(): string
    {
        return $this->store->lagoon_deploy_region_id_ext;
    }

    /**
     * Get the Lagoon project prefix attribute
     */
    public function getLagoonDeployProjectPrefixAttribute(): string
    {
        return $this->store->lagoon_deploy_project_prefix;
    }

    /**
     * Get the Lagoon deploy private key attribute
     */
    public function getLagoonDeployPrivateKeyAttribute(): string
    {
        return $this->store->lagoon_deploy_private_key;
    }

    /**
     * Get the Lagoon deploy organization ID attribute
     */
    public function getLagoonDeployOrganizationIdExtAttribute(): string
    {
        return $this->store->lagoon_deploy_organization_id_ext;
    }

    /**
     * Get the Amazee AI backend region ID attribute
     */
    public function getAmazeeAiBackendRegionIdExtAttribute(): string
    {
        return $this->store->amazee_ai_backend_region_id_ext;
    }

    /**
     * Get the number of unallocated instances for this app
     */
    public function getUnallocatedInstancesCountAttribute(): int
    {
        return $this->instances()
            ->whereNull('user_group_id')
            ->count();
    }

    /**
     * Determine if we need more unallocated instances
     */
    public function getNeedsMoreUnallocatedInstancesAttribute(): bool
    {
        return $this->unallocated_instances_count < $this->target_unallocated_app_instances;
    }

    /**
     * Get all unallocated instances of this store app
     */
    public function unallocatedInstances(): HasMany
    {
        return $this->instances()->whereNull('user_group_id');
    }

    /**
     * Get all allocated instances of this store app
     */
    public function allocatedInstances(): HasMany
    {
        return $this->instances()->whereNotNull('user_group_id');
    }

    /**
     * Get all variables for this store app
     */
    public function variables(): MorphMany
    {
        return $this->morphMany(PolydockVariable::class, 'variabled');
    }
} 