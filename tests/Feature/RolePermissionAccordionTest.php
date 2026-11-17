<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RolePermissionAccordionTest extends TestCase
{
    #[Test]
    public function role_and_permission_groups_use_nested_accessible_accordions(): void
    {
        $roles = (string) file_get_contents(resource_path('views/admin/roles/index.blade.php'));
        $permissions = (string) file_get_contents(resource_path('views/admin/roles/_permission-fields.blade.php'));

        $this->assertStringContainsString('aria-controls="role-panel-', $roles);
        $this->assertStringContainsString('@keydown.escape.window', $roles);
        $this->assertStringContainsString('data-role-permission-accordion="capabilities"', $permissions);
        $this->assertStringContainsString('data-role-permission-accordion="routes"', $permissions);
        $this->assertStringContainsString('data-route-permission-group', $permissions);
        $this->assertStringContainsString(':aria-expanded="open.toString()"', $permissions);
    }
}
