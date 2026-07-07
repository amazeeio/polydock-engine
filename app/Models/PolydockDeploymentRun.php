<?php

namespace App\Models;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Override;

/**
 * A single triggered rollout of Lagoon redeploys across one or more app instances.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $polydock_store_app_id
 * @property int|null $triggered_by_user_id
 * @property PolydockDeploymentRunTriggerSourceEnum $trigger_source
 * @property string|null $lagoon_bulk_id
 * @property PolydockDeploymentRunStatusEnum $status
 * @property int $total_count
 * @property int $success_count
 * @property int $failed_count
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $last_polled_at
 * @property int $poll_attempts
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property PolydockStoreApp|null $storeApp
 * @property User|null $triggeredByUser
 * @property Collection<int, PolydockAppInstance> $instances
 */
class PolydockDeploymentRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'polydock_store_app_id',
        'triggered_by_user_id',
        'trigger_source',
        'lagoon_bulk_id',
        'status',
        'total_count',
        'success_count',
        'failed_count',
        'started_at',
        'completed_at',
        'last_polled_at',
        'poll_attempts',
    ];

    protected $casts = [
        'trigger_source' => PolydockDeploymentRunTriggerSourceEnum::class,
        'status' => PolydockDeploymentRunStatusEnum::class,
        'total_count' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'poll_attempts' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_polled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    #[Override]
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function storeApp(): BelongsTo
    {
        return $this->belongsTo(PolydockStoreApp::class, 'polydock_store_app_id');
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    /**
     * @return HasMany<PolydockAppInstance, $this>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(PolydockAppInstance::class, 'deployment_run_id');
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Whether the currently-authenticated user may manage deployments (trigger
     * redeploys, view the deployments dashboard). super_admin always may; other
     * roles need the manage_polydock_deployments permission.
     */
    public static function currentUserCanManage(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super_admin') || $user->can('manage_polydock_deployments'));
    }
}
