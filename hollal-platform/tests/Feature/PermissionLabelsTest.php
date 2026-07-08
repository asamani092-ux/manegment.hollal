<?php

namespace Tests\Feature;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_seeded_permission_has_an_arabic_label(): void
    {
        $labels = config('permission_labels.labels');

        foreach (PermissionSeeder::PERMISSIONS as $permission) {
            $this->assertArrayHasKey(
                $permission,
                $labels,
                "Missing Arabic label for permission: {$permission}"
            );

            $label = $labels[$permission];

            $this->assertIsString($label);
            $this->assertNotSame('', trim($label), "Empty Arabic label for permission: {$permission}");
            $this->assertNotSame($permission, $label, "Label falls back to raw name for: {$permission}");
        }
    }

    public function test_every_permission_module_has_an_arabic_group_header(): void
    {
        $groups = config('permission_labels.groups');

        foreach (PermissionSeeder::PERMISSIONS as $permission) {
            $module = explode('.', $permission, 2)[0];

            $this->assertArrayHasKey(
                $module,
                $groups,
                "Missing Arabic group header for module: {$module}"
            );

            $this->assertNotSame('', trim($groups[$module]));
        }
    }
}
