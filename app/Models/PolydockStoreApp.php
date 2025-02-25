<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\PolydockStoreAppStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

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
        'lagoon_deploy_region_id',
        'lagoon_project_prefix',
        'status',
        'uuid',
    ];

    protected $casts = [
        'status' => PolydockStoreAppStatusEnum::class,
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
} 