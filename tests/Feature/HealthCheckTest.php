<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    // ── Liveness ─────────────────────────────────────────────────────────────

    public function test_liveness_returns_200(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'version', 'uptime'])
            ->assertJsonPath('status', 'ok');
    }

    public function test_liveness_does_not_require_authentication(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
    }

    public function test_liveness_includes_version(): void
    {
        $response = $this->get('/health');

        $this->assertNotNull($response->json('version'));
    }

    public function test_liveness_includes_uptime(): void
    {
        $response = $this->get('/health');

        $this->assertIsInt($response->json('uptime'));
    }

    // ── Readiness ─────────────────────────────────────────────────────────────

    public function test_readiness_returns_200_when_all_healthy(): void
    {
        $response = $this->get('/health/ready');

        $response->assertStatus(200)
            ->assertJsonStructure(['db', 'cache', 'queue'])
            ->assertJsonPath('db', 'ok')
            ->assertJsonPath('cache', 'ok')
            ->assertJsonPath('queue', 'ok');
    }

    public function test_readiness_does_not_require_authentication(): void
    {
        $response = $this->get('/health/ready');

        $response->assertStatus(200);
    }

    public function test_readiness_returns_503_when_db_is_down(): void
    {
        // Simulate DB failure
        DB::shouldReceive('connection->getPdo')
            ->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->get('/health/ready');

        $response->assertStatus(503)
            ->assertJsonPath('db', 'fail');
    }

    public function test_readiness_returns_503_when_cache_is_down(): void
    {
        // Simulate cache failure
        Cache::shouldReceive('put')->andThrow(new \RuntimeException('Cache unavailable'));

        $response = $this->get('/health/ready');

        $response->assertStatus(503)
            ->assertJsonPath('cache', 'fail');
    }

    public function test_readiness_response_has_all_three_health_keys(): void
    {
        $response = $this->get('/health/ready');

        $data = $response->json();
        $this->assertArrayHasKey('db', $data);
        $this->assertArrayHasKey('cache', $data);
        $this->assertArrayHasKey('queue', $data);

        $this->assertContains($data['db'], ['ok', 'fail']);
        $this->assertContains($data['cache'], ['ok', 'fail']);
        $this->assertContains($data['queue'], ['ok', 'fail']);
    }
}
