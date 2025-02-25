<?php

namespace App\Models;

use App\Enums\UserRemoteRegistrationStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Jobs\ProcessUserRemoteRegistration;
use App\Events\UserRemoteRegistrationCreated;
use Illuminate\Support\Facades\Log;
class UserRemoteRegistration extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'user_id',
        'user_group_id',
        'request_data',
        'result_data',
        'status',
        'uuid',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */ 
    protected $casts = [
        'request_data' => 'array',
        'result_data' => 'array',
        'status' => UserRemoteRegistrationStatusEnum::class,
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
}
