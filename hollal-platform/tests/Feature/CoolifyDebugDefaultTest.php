<?php

namespace Tests\Feature;

use Tests\TestCase;

class CoolifyDebugDefaultTest extends TestCase
{
    public function test_coolify_compose_does_not_default_debug_to_true(): void
    {
        $path = base_path('docker-compose.coolify.yml');
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('APP_DEBUG:-true', $contents);
        $this->assertStringContainsString('APP_DEBUG:-false', $contents);
        $this->assertStringContainsString('APP_DEBUG=true', file_get_contents(base_path('.env.example')));
    }
}
