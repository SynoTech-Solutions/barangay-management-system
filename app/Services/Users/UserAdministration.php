<?php

namespace App\Services\Users;

use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\RoleScope;
use App\Exceptions\BmsException;
use App\Models\Barangay;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class UserAdministration
{
    private const array AUDITED_ATTRIBUTES = ['name', 'username', 'designation', 'email', 'is_active'];

    public function __construct(
        private AuditTrail $audit,
        private PermissionResolver $permissions,
    ) {}

    /**
     * @return Collection<int, Role>
     */
    public function assignableRoles(?Barangay $barangay): Collection
    {
        $scope = $barangay === null ? RoleScope::Global : RoleScope::Barangay;

        return Role::query()
            ->where('scope', $scope)
            ->where(fn ($query) => $query->whereNull('barangay_id')->when(
                $barangay !== null,
                fn ($inner) => $inner->orWhere('barangay_id', $barangay?->id),
            ))
            ->orderBy('is_system', 'desc')
            ->orderBy('key')
            ->get();
    }

    /**
     * @param  array{name: string, username: string, designation?: string|null, email?: string|null, password: string}  $attributes
     * @param  list<int>  $roleIds
     */
    public function create(?Barangay $barangay, array $attributes, array $roleIds, User $actor): User
    {
        return DB::transaction(function () use ($barangay, $attributes, $roleIds, $actor): User {
            $user = new User;
            $user->fill($attributes);
            $user->barangay_id = $barangay?->id;
            $user->is_active = true;
            $user->save();

            $this->replaceRoles($user, $roleIds);

            $this->audit->record(
                AuditAction::Created,
                $user,
                after: [...$this->snapshot($user), 'role_ids' => $roleIds],
                barangayId: $barangay?->id,
                actorId: $actor->id,
            );

            return $user;
        });
    }

    /**
     * @param  array{name: string, username: string, designation?: string|null, email?: string|null}  $attributes
     */
    public function update(User $user, array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($user, $attributes, $actor): User {
            $before = $this->snapshot($user);
            $user->fill($attributes)->save();

            $this->audit->record(AuditAction::Updated, $user, $before, $this->snapshot($user), $user->barangay_id, $actor->id);

            return $user;
        });
    }

    /**
     * @param  list<int>  $roleIds
     */
    public function setRoles(User $user, array $roleIds, User $actor): void
    {
        if ($user->is($actor)) {
            throw BmsException::of(ErrorCode::CannotRemoveOwnAdminRole);
        }

        DB::transaction(function () use ($user, $roleIds, $actor): void {
            $before = $this->roleIds($user);
            $this->replaceRoles($user, $roleIds);

            $this->audit->record(
                AuditAction::RolesChanged,
                $user,
                ['role_ids' => $before],
                ['role_ids' => $this->roleIds($user)],
                $user->barangay_id,
                $actor->id,
            );
        });

        $this->permissions->forget();
    }

    public function setActive(User $user, bool $active, User $actor): void
    {
        if (! $active && $user->is($actor)) {
            throw BmsException::of(ErrorCode::CannotDeactivateSelf);
        }

        DB::transaction(function () use ($user, $active, $actor): void {
            $user->is_active = $active;
            $user->save();

            $this->audit->record(
                $active ? AuditAction::Reactivated : AuditAction::Deactivated,
                $user,
                barangayId: $user->barangay_id,
                actorId: $actor->id,
            );
        });

        $this->permissions->forget();
    }

    public function resetPassword(User $user, string $password, User $actor): void
    {
        DB::transaction(function () use ($user, $password, $actor): void {
            $user->password = $password;
            $user->remember_token = null;
            $user->save();

            $this->audit->record(AuditAction::PasswordReset, $user, barangayId: $user->barangay_id, actorId: $actor->id);
        });
    }

    /**
     * @param  list<int>  $roleIds
     */
    private function replaceRoles(User $user, array $roleIds): void
    {
        $barangay = $user->barangay;
        $assignable = $this->assignableRoles($barangay)->pluck('id')->all();

        foreach ($roleIds as $roleId) {
            if (! in_array($roleId, $assignable, true)) {
                throw BmsException::of(ErrorCode::RoleNotAssignable, ['role' => $roleId]);
            }
        }

        UserRole::query()->where('user_id', $user->id)->delete();

        foreach (array_unique($roleIds) as $roleId) {
            UserRole::query()->create([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'barangay_id' => $user->barangay_id,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function roleIds(User $user): array
    {
        return array_values(UserRole::query()->where('user_id', $user->id)->orderBy('role_id')->pluck('role_id')->map(fn ($id): int => (int) $id)->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return $user->only(self::AUDITED_ATTRIBUTES);
    }
}
