<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeployMarkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_marker_hides_migration_error_text(): void
    {
        file_put_contents(storage_path('app/deploy-status.json'), json_encode([
            'migrate_status' => 'failed',
            'pending_migrations_count' => 2,
            'error_lines' => ['SQLSTATE secret hollal password leaked'],
        ]));

        $response = $this->get(route('hr.deploy-marker'));

        $response->assertOk();
        $response->assertJson([
            'migrate_status' => 'failed',
            'pending_migrations_count' => 2,
        ]);
        $payload = $response->json();
        $this->assertIsString($payload['marker'] ?? null);
        $this->assertStringStartsWith('hr-fix-', $payload['marker']);
        $this->assertArrayNotHasKey('error_lines', $payload);
        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertStringNotContainsString('password', $response->getContent());
    }
}
