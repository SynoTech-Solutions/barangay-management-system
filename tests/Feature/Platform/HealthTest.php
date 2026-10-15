<?php

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_readiness_reports_each_dependency(): void
    {
        $this->getJson(route('health.ready'))
            ->assertOk()
            ->assertJson(['status' => 'ok', 'checks' => ['database' => true, 'cache' => true]]);
    }
}
