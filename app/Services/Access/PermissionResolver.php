<?php

namespace App\Services\Access;

use App\Enums\Permission;
use App\Enums\RoleScope;
use App\Models\User;
use App\Tenancy\CurrentBarangay;
use Illuminate\Support\Facades\DB;

final class PermissionResolver
{
    /**
     * @var array<string, array<string, true>>
     */
    private array $memo = [];

    public function __construct(private readonly CurrentBarangay $currentBarangay) {}

    public function has(User $user, Permission $permission, ?int $barangayId = null): bool
    {
        return isset($this->permissionsFor($user, $barangayId ?? $this->currentBarangay->id())[$permission->value]);
    }

    /**
     * @return list<string>
     */
    public function list(User $user, ?int $barangayId = null): array
    {
        return array_keys($this->permissionsFor($user, $barangayId ?? $this->currentBarangay->id()));
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    /**
     * @return array<string, true>
     */
    private function permissionsFor(User $user, ?int $barangayId): array
    {
        if (! $user->is_active) {
            return [];
        }

        $key = $user->id.':'.($barangayId ?? '-');

        return $this->memo[$key] ??= $this->load($user, $barangayId);
    }

    /**
     * @return array<string, true>
     */
    private function load(User $user, ?int $barangayId): array
    {
        $rows = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where(function ($query) use ($barangayId): void {
                $query->whereNull('user_roles.barangay_id');
                if ($barangayId !== null) {
                    $query->orWhere('user_roles.barangay_id', $barangayId);
                }
            })
            ->select(['role_permissions.permission', 'roles.scope'])
            ->get();

        $granted = [];
        foreach ($rows as $row) {
            $permission = Permission::tryFrom((string) $row->permission);
            if ($permission === null) {
                continue;
            }
            if ($permission->isGlobalOnly() && $row->scope !== RoleScope::Global->value) {
                continue;
            }
            $granted[$permission->value] = true;
        }

        return $granted;
    }
}
