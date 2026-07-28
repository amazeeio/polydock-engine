<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An admin-managed hosted /f/{slug} form: which form class serves it, the
 * texts it displays, and the store apps it is allowed to provision.
 *
 * @property int $id
 * @property string $slug
 * @property string $form_class
 * @property bool $enabled
 * @property string $title
 * @property string|null $description
 * @property string|null $notice
 * @property string|null $disclaimer
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PolydockHostedForm extends Model
{
    use LogsActivity;

    protected $fillable = [
        'slug',
        'form_class',
        'enabled',
        'title',
        'description',
        'notice',
        'disclaimer',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * Plain-text fields rendered into headings and <head> tags — HTML is
     * stripped on write so an admin paste mistake can't inject markup.
     *
     * @var list<string>
     */
    private const PLAIN_TEXT_FIELDS = ['title', 'seo_title', 'seo_description'];

    #[\Override]
    public function setAttribute($key, $value)
    {
        if (in_array($key, self::PLAIN_TEXT_FIELDS, true) && is_string($value)) {
            $value = trim(strip_tags($value));
        }

        return parent::setAttribute($key, $value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The store apps this form is allowed to offer and provision.
     */
    public function storeApps(): BelongsToMany
    {
        return $this->belongsToMany(
            PolydockStoreApp::class,
            'polydock_hosted_form_store_app',
        );
    }
}
