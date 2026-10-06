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
        $this->assertTrue(
            str_starts_with((string) $payload['marker'], 'hr-fix-')
            || str_starts_with((string) $payload['marker'], 'clarity-')
            || str_starts_with((string) $payload['marker'], 'org-')
        );
        $this->assertArrayNotHasKey('error_lines', $payload);
        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertStringNotContainsString('password', $response->getContent());
    }

    public function test_entrypoint_exports_migrate_status_to_the_status_file(): void
    {
        $script = file_get_contents(base_path('docker/entrypoint.sh'));

        $this->assertStringContainsString('MIGRATE_STATUS="$MIGRATE_STATUS"', $script);
        $this->assertStringContainsString('PENDING="$PENDING"', $script);
    }
}
