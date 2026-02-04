<?php

namespace App\Models;

use App\Enums\PolydockVariableScopeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Crypt;

class PolydockVariable extends Model
{
    protected $fillable = [
        'name',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'scope' => PolydockVariableScopeEnum::class,
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get the decrypted value if encrypted, or raw value if not
     */
    public function getDecryptedValue(): ?string
    {
        if (! $this->value) {
            return null;
        }

        return $this->is_encrypted ? Crypt::decryptString($this->value) : $this->value;
    }

    /**
     * Set the value, encrypting if needed
     */
    public function setEncryptedValue(?string $value, bool $encrypt = false): self
    {
        $this->value = $value ? ($encrypt ? Crypt::encryptString($value) : $value) : null;
        $this->is_encrypted = $encrypt;

        return $this;
    }

    /**
     * Get the parent model (Store, StoreApp, or AppInstance)
     */
    public function variabled(): MorphTo
    {
        return $this->morphTo();
    }
}
