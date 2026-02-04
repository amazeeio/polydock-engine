<?php

namespace App\Models;

use App\Enums\UserRemoteRegistrationStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Jobs\ProcessUserRemoteRegistration;
use App\Events\UserRemoteRegistrationCreated;
use Illuminate\Support\Facades\Log;
use App\Events\UserRemoteRegistrationStatusChanged;
use App\Traits\HasWebhookSensitiveData;
use App\Enums\UserRemoteRegistrationType;

class UserRemoteRegistration extends Model
{
    use HasWebhookSensitiveData;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'user_id',
        'user_group_id',
        'polydock_store_app_id',
        'request_data',
        'result_data',
        'status',
        'uuid',
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */ 
    protected $casts = [
        'status' => UserRemoteRegistrationStatusEnum::class,
        'type' => UserRemoteRegistrationType::class,
        'request_data' => 'array',
        'result_data' => 'array',
        'polydock_store_app_id' => 'integer:nullable',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'register_only_captures',
        'register_simulate_round_robin',
        'register_simulate_error',
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

        static::created(function ($model) {
            Log::info('User remote registration created', ['registration' => $model->toArray()]);
            UserRemoteRegistrationCreated::dispatch($model);
        });

        static::updating(function ($model) {
            // If status is changing, fire the event
            if ($model->isDirty('status')) {
                UserRemoteRegistrationStatusChanged::dispatch(
                    $model,
                    $model->getOriginal('status')
                );
            }
        });
    }

    /**
     * Get the user that the remote registration belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */ 
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user group that the remote registration belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */     
    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class);
    }

    /**
     * Get the store app that the remote registration belongs to.
     */
    public function storeApp(): BelongsTo
    {
        return $this->belongsTo(PolydockStoreApp::class, 'polydock_store_app_id')
            ->withDefault(null);
    }

    /**
     * Get a value from the request data by key
     *
     * @param string $key The key to look for in the request data
     * @return mixed|null The value if found, null otherwise
     */
    public function getRequestValue(string $key): mixed
    {
        return data_get($this->request_data, $key);
    }

    /**
     * Get a value from the result data by key
     *
     * @param string $key The key to look for in the result data
     * @return mixed|null The value if found, null otherwise
     */
    public function getResultValue(string $key): mixed
    {
        return data_get($this->result_data, $key);
    }

    /**
     * Set a value in the result data by key
     *
     * @param string $key The key to set in the result data
     * @param mixed $value The value to set
     * @return self
     */
    public function setResultValue(string $key, mixed $value): self
    {
        $resultData = $this->result_data ?? [];
        data_set($resultData, $key, $value);
        $this->result_data = $resultData;
        return $this;
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

    /**
     * Get the register only captures attribute
     */
    public function getRegisterOnlyCapturesAttribute(): bool
    {
        return config('polydock.register_only_captures', false);
    }

    /**
     * Get the register simulate round robin attribute
     */
    public function getRegisterSimulateRoundRobinAttribute(): bool
    {
        return config('polydock.register_simulate_round_robin', false);
    }

    /**
     * Get the register simulate error attribute
     */
    public function getRegisterSimulateErrorAttribute(): bool
    {
        return config('polydock.register_simulate_error', false);
    }

    /**
     * Get the app instance associated with this registration
     */
    public function appInstance(): BelongsTo
    {
        return $this->belongsTo(PolydockAppInstance::class, 'polydock_app_instance_id');
    }

    public function getDataAttribute(): array
    {
        return [
            'request_data' => $this->request_data,
            'result_data' => $this->result_data,
        ];
    }
}
 