<?php

namespace App\Models;

use App\Enums\PolydockStoreStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PolydockStore extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'listed_in_marketplace',
    ];

    protected $casts = [
        'status' => PolydockStoreStatusEnum::class,
        'listed_in_marketplace' => 'boolean',
    ];

    public function apps(): HasMany
    {
        return $this->hasMany(PolydockStoreApp::class);
    }
} 