<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PolydockProductType extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'name',
        'slug',
    ];

    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (empty($model->slug) || ($model->isDirty('name') && ! $model->isDirty('slug'))) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Get the store apps that have this product type.
     */
    public function storeApps(): HasMany
    {
        return $this->hasMany(PolydockStoreApp::class, 'polydock_product_type_id');
    }
}
