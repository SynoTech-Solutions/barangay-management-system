<?php

namespace App\Services\Access;

use App\Domain\Access\RoleCatalog;
use App\Enums\LogEvent;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RoleSynchronizer
{
    public function sync(): int
    {
        return DB::transaction(function (): int {
            foreach (SystemRole::cases() as $systemRole) {
                $role = Role::query()->updateOrCreate(
                    ['barangay_id' => null, 'key' => $systemRole->value],
                    ['scope' => $systemRole->scope(), 'is_system' => true],
                );

                $permissions = array_map(
                    fn ($permission): string => $permission->value,
                    RoleCatalog::permissionsFor($systemRole),
                );

                RolePermission::query()
                    ->where('role_id', $role->id)
                    ->whereNotIn('permission', $permissions)
                    ->delete();

                RolePermission::query()->insertOrIgnore(array_map(
                    fn (string $permission): array => ['role_id' => $role->id, 'permission' => $permission],
                    $permissions,
                ));
            }

            Log::info(LogEvent::RolesSynchronized->value, ['roles' => count(SystemRole::cases())]);

            return count(SystemRole::cases());
        });
    }

    public function systemRole(SystemRole $role): Role
    {
        return Role::query()
            ->whereNull('barangay_id')
            ->where('key', $role->value)
            ->firstOrFail();
    }
}
