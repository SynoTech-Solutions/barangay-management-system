<?php

namespace Database\Factories;

use App\Enums\SystemRole;
use App\Models\Barangay;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Access\RoleSynchronizer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public const string PASSWORD = 'correct-horse-battery-9';

    private const int REMEMBER_TOKEN_LENGTH = 10;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => null,
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'designation' => null,
            'is_active' => true,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(self::PASSWORD),
            'remember_token' => Str::random(self::REMEMBER_TOKEN_LENGTH),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function forBarangay(Barangay $barangay): static
    {
        return $this->state(fn () => ['barangay_id' => $barangay->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withRole(SystemRole $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $synchronizer = app(RoleSynchronizer::class);
            $synchronizer->sync();

            UserRole::query()->create([
                'user_id' => $user->id,
                'role_id' => $synchronizer->systemRole($role)->id,
                'barangay_id' => $user->barangay_id,
            ]);
        });
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
