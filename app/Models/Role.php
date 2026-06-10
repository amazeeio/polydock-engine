<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string $name
 * @property string|null $label
 * @property string $guard_name
 * @property string $display_name
 */
class Role extends SpatieRole
{
    use LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'label', 'guard_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the display name for this role (label if set, otherwise formatted name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->label ?? str($this->name)->headline()->toString();
    }
}
