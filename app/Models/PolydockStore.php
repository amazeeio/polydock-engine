<?php

namespace App\Models;

use App\Enums\PolydockStoreStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Traits\HasPolydockVariables;

class PolydockStore extends Model
{
    use HasFactory, HasPolydockVariables;

    protected $fillable = [
        'name',
        'status',
        'listed_in_marketplace',
        'lagoon_deploy_region_id_ext',
        'lagoon_deploy_project_prefix',
        'lagoon_deploy_private_key',
        'lagoon_deploy_organization_id_ext',
        'amazee_ai_backend_region_id_ext',
        'lagoon_deploy_group_name',
    ];

    protected $casts = [
        'status' => PolydockStoreStatusEnum::class,
        'listed_in_marketplace' => 'boolean',
    ];

    public function apps(): HasMany
    {
        return $this->hasMany(PolydockStoreApp::class);
    }

    /**
     * Get the webhooks for this store
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(PolydockStoreWebhook::class);
    }

    /**
     * Get all variables for this store
     */
    public function variables(): MorphMany
    {
        return $this->morphMany(PolydockVariable::class, 'variabled');
    }
} 