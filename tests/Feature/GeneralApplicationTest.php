<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralApplicationTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_redirect_for_the_front_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
    }
}
