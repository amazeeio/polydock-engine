<?php

namespace App\Models;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Traits\HasPolydockVariables;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class PolydockStoreApp extends Model
{
    use HasFactory;
    use HasPolydockVariables;

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
        'lagoon_post_deploy_script',
        'lagoon_pre_upgrade_script',
        'lagoon_upgrade_script',
        'lagoon_post_upgrade_script',
        'lagoon_claim_script',
        'lagoon_pre_remove_script',
        'lagoon_remove_script',
        'email_subject_line',
        'email_body_markdown',
        'status',
        'uuid',
        'available_for_trials',
        'target_unallocated_app_instances',
        'trial_duration_days',
        'send_midtrial_email',
        'midtrial_email_subject',
        'midtrial_email_markdown',
        'send_one_day_left_email',
        'one_day_left_email_subject',
        'one_day_left_email_markdown',
        'send_trial_complete_email',
        'trial_complete_email_subject',
        'trial_complete_email_markdown',
        'lagoon_post_deploy_service',
        'lagoon_post_deploy_container',
        'lagoon_pre_upgrade_service',
        'lagoon_pre_upgrade_container',
        'lagoon_upgrade_service',
        'lagoon_upgrade_container',
        'lagoon_post_upgrade_service',
        'lagoon_post_upgrade_container',
        'lagoon_claim_service',
        'lagoon_claim_container',
        'lagoon_pre_remove_service',
        'lagoon_pre_remove_container',
        'lagoon_remove_service',
        'lagoon_remove_container',
    ];

    protected $casts = [
        'status' => PolydockStoreAppStatusEnum::class,
        'available_for_trials' => 'boolean',
        'target_unallocated_app_instances' => 'integer',
        'send_midtrial_email' => 'boolean',
        'send_one_day_left_email' => 'boolean',
        'send_trial_complete_email' => 'boolean',
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
        'lagoon_deploy_group_name',
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

    /**
     * Get the Lagoon deploy group name attribute
     */
    public function getLagoonDeployGroupNameAttribute(): ?string
    {
        return $this->store->lagoon_deploy_group_name;
    }
}
