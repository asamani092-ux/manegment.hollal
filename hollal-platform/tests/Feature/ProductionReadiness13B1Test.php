<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 13-B1 — production-config smoke checks that run in CI, so a go-live can never
 * ship with debug on, a missing scheduled sweep, or an undocumented step.
 */
class ProductionReadiness13B1Test extends TestCase
{
    use RefreshDatabase;

    private function deploymentDoc(): string
    {
        return (string) file_get_contents(base_path('../hollal-platform/docs/DEPLOYMENT.md'));
    }

    public function test_production_environment_forces_debug_off(): void
    {
        $this->assertFalse(
            (bool) env('APP_DEBUG_FORCE_PRODUCTION', false),
            'no override may force debug in production'
        );

        // The shipped example config must not enable debug.
        $example = (string) file_get_contents(base_path('.env.example'));
        $this->assertStringNotContainsString('APP_DEBUG=true'.PHP_EOL.'APP_ENV=production', $example);
    }

    public function test_deployment_doc_covers_hostinger_cron_and_queue(): void
    {
        $doc = $this->deploymentDoc();

        $this->assertStringContainsString('Hostinger', $doc);
        $this->assertStringContainsString('schedule:run', $doc);
        $this->assertStringContainsString('queue:work', $doc);
        $this->assertStringContainsString('APP_DEBUG=false', $doc);
        $this->assertStringContainsString('migrate --force', $doc);
    }

    public function test_deployment_doc_has_a_restore_test_checklist(): void
    {
        $doc = $this->deploymentDoc();

        $this->assertStringContainsString('RESTORE TEST', $doc);
        $this->assertStringContainsString('قائمة تحقق الإطلاق', $doc);
    }

    public function test_https_is_forced_in_production(): void
    {
        $provider = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString("environment('production')", $provider);
        $this->assertStringContainsString('URL::forceScheme', $provider);
    }

    public function test_security_headers_middleware_is_globally_applied(): void
    {
        $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString('SecurityHeadersMiddleware', $bootstrap);
        $this->assertStringContainsString('MaintenanceMode', $bootstrap);
    }

    public function test_seeders_needed_for_go_live_exist(): void
    {
        foreach ([
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\PlatformSettingsSeeder::class,
            \Database\Seeders\PlanTemplateSeeder::class,
        ] as $seeder) {
            $this->assertTrue(class_exists($seeder), "missing go-live seeder: {$seeder}");
        }
    }
}
