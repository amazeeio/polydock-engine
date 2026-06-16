<?php

namespace Tests\Unit\Models;

use App\Models\PolydockProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolydockProductTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_slug_from_name_on_creation(): void
    {
        $productType = PolydockProductType::create([
            'name' => 'Amazee Claw',
        ]);

        $this->assertEquals('amazee-claw', $productType->slug);
    }

    public function test_it_regenerates_slug_on_name_update(): void
    {
        $productType = PolydockProductType::create([
            'name' => 'Amazee Claw',
        ]);

        $productType->name = 'New Amazee Claw Name';
        $productType->save();

        $this->assertEquals('new-amazee-claw-name', $productType->slug);
    }

    public function test_it_allows_setting_custom_slug_on_creation(): void
    {
        $productType = PolydockProductType::create([
            'name' => 'Amazee Claw',
            'slug' => 'custom-claw-slug',
        ]);

        $this->assertEquals('custom-claw-slug', $productType->slug);
    }

    public function test_it_allows_setting_custom_slug_on_update(): void
    {
        $productType = PolydockProductType::create([
            'name' => 'Amazee Claw',
        ]);

        $productType->slug = 'custom-slug-updated';
        $productType->save();

        $this->assertEquals('custom-slug-updated', $productType->slug);
    }

    public function test_it_allows_updating_both_name_and_slug_simultaneously(): void
    {
        $productType = PolydockProductType::create([
            'name' => 'Amazee Claw',
        ]);

        $productType->name = 'Super Amazee Claw';
        $productType->slug = 'super-claw';
        $productType->save();

        $this->assertEquals('super-claw', $productType->slug);
    }
}
