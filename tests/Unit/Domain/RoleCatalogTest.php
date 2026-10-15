<?php

namespace Tests\Unit\Domain;

use App\Domain\Access\RoleCatalog;
use App\Enums\Permission;
use App\Enums\RoleScope;
use App\Enums\SystemRole;
use PHPUnit\Framework\TestCase;

class RoleCatalogTest extends TestCase
{
    public function test_barangay_roles_never_hold_global_only_permissions(): void
    {
        foreach (SystemRole::cases() as $role) {
            if ($role->scope() !== RoleScope::Barangay) {
                continue;
            }
            foreach (RoleCatalog::permissionsFor($role) as $permission) {
                $this->assertFalse($permission->isGlobalOnly(), "{$role->value} must not hold {$permission->value}");
            }
        }
    }

    public function test_central_admin_holds_every_permission(): void
    {
        $this->assertCount(count(Permission::cases()), RoleCatalog::permissionsFor(SystemRole::CentralAdmin));
    }

    public function test_viewer_cannot_change_anything(): void
    {
        $writes = array_filter(
            RoleCatalog::permissionsFor(SystemRole::Viewer),
            fn (Permission $permission): bool => ! str_ends_with($permission->value, '.view'),
        );

        $this->assertSame([], array_values($writes));
    }

    public function test_permissions_are_unique_per_role(): void
    {
        foreach (SystemRole::cases() as $role) {
            $values = array_map(fn (Permission $permission): string => $permission->value, RoleCatalog::permissionsFor($role));
            $this->assertSame(array_values(array_unique($values)), $values);
        }
    }

    public function test_only_blotter_handlers_can_read_blotters(): void
    {
        $holders = array_values(array_map(
            fn (SystemRole $role): string => $role->value,
            array_filter(SystemRole::cases(), fn (SystemRole $role): bool => in_array(Permission::BlotterView, RoleCatalog::permissionsFor($role), true)),
        ));

        $this->assertSame([SystemRole::CentralAdmin->value, SystemRole::BarangayAdmin->value, SystemRole::Secretary->value, SystemRole::Clerk->value], $holders);
    }
}
