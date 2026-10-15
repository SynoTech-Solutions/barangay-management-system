<?php

namespace Database\Factories;

use App\Enums\AccountGroupType;
use App\Enums\AccountKind;
use App\Models\Account;
use App\Models\Barangay;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'kind' => AccountKind::Group,
            'group_type' => AccountGroupType::Expense,
            'code' => 'G'.fake()->unique()->numberBetween(100, 999999),
            'name' => 'Account '.fake()->unique()->numberBetween(1, 999999),
            'level' => 0,
            'is_locked' => false,
            'is_active' => true,
        ];
    }

    public function group(AccountGroupType $type): static
    {
        return $this->state(fn () => ['kind' => AccountKind::Group, 'group_type' => $type, 'parent_id' => null, 'group_id' => null, 'level' => 0]);
    }

    public function under(Account $parent, AccountKind $kind = AccountKind::Title): static
    {
        return $this->state(fn () => [
            'barangay_id' => $parent->barangay_id,
            'kind' => $kind,
            'group_type' => null,
            'parent_id' => $parent->id,
            'group_id' => $parent->groupId(),
            'level' => $parent->level + 1,
            'code' => $parent->code.'-'.fake()->unique()->numberBetween(10, 999999),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }
}
