<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_framework_health_endpoint_is_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_application_health_endpoint_returns_json(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }
}
