<?php

namespace Database\Seeders;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Forms\DrupalAIDemoDrupalOrgForm;
use App\Forms\GenericHostedForm;
use App\Models\PolydockHostedForm;
use App\Models\PolydockStoreApp;
use Illuminate\Database\Seeder;

/**
 * Seeds the admin-managed hosted form records for the forms that previously
 * lived in code. Idempotent (keyed by slug) and safe to run on production:
 * `php artisan db:seed --class=HostedFormsSeeder`.
 */
class HostedFormsSeeder extends Seeder
{
    public function run(): void
    {
        $drupalOrg = PolydockHostedForm::updateOrCreate(
            ['slug' => 'drupal-ai-demo'],
            [
                'form_class' => DrupalAIDemoDrupalOrgForm::class,
                'enabled' => true,
                'title' => 'Private Drupal AI Demo on drupal.org',
                'seo_title' => 'Drupal AI Demo on drupal.org by amazee.ai',
                'seo_description' => 'Try Drupal AI with our custom demo experiences designed for developers and content editors new to Drupal AI on drupal.org.',
            ],
        );

        // Before allowlisting, this form could offer every available trial
        // app in a public store — attach those so the same apps stay selectable.
        $previouslyOfferedAppIds = PolydockStoreApp::query()
            ->where('status', PolydockStoreAppStatusEnum::AVAILABLE)
            ->where('available_for_trials', true)
            ->whereHas('store', function ($query) {
                $query->where('status', PolydockStoreStatusEnum::PUBLIC);
            })
            ->pluck('id');

        $drupalOrg->storeApps()->syncWithoutDetaching($previouslyOfferedAppIds);

        // No apps attached: stays locked until the app is created and attached
        // in the admin panel.
        PolydockHostedForm::updateOrCreate(
            ['slug' => 'drupal-ai-partners-demo'],
            [
                'form_class' => GenericHostedForm::class,
                'enabled' => true,
                'title' => 'Drupal AI Initiative - Partners Demo',
                'description' => 'You can spin up a new amazee.io hosted demo of the Drupal AI partners demo, it is based on the code at: <a href="https://gitlab.com/drupal-infrastructure/ai/drupal-ai-starter-template" target="_blank" rel="noopener">https://gitlab.com/drupal-infrastructure/ai/drupal-ai-starter-template</a>',
                'notice' => 'Please keep this private and only for the members of the Drupal AI initiative.',
                'seo_title' => 'Drupal AI Initiative - Partners Demo by amazee.io',
                'seo_description' => 'Spin up a new amazee.io hosted demo of the Drupal AI partners demo for members of the Drupal AI initiative.',
            ],
        );
    }
}
