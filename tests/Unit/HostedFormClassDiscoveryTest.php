<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Forms\DrupalAIDemoDrupalOrgForm;
use App\Forms\GenericHostedForm;
use App\Services\HostedFormClassDiscovery;
use Tests\TestCase;

class HostedFormClassDiscoveryTest extends TestCase
{
    public function test_form_label_attribute_overrides_the_generated_label(): void
    {
        $classes = app(HostedFormClassDiscovery::class)->getAvailableFormClasses();

        $this->assertSame('Generic Hosted Form', $classes[GenericHostedForm::class]);
        $this->assertSame('Drupal AI Demo (drupal.org)', $classes[DrupalAIDemoDrupalOrgForm::class]);
    }
}
