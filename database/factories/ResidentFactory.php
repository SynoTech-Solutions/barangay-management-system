<?php

namespace Database\Factories;

use App\Domain\Residents\NameNormalizer;
use App\Enums\CivilStatus;
use App\Enums\Gender;
use App\Models\Barangay;
use App\Models\Resident;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    private const int MINIMUM_AGE = 1;

    private const int MAXIMUM_AGE = 90;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $last = fake()->lastName();
        $first = fake()->firstName();

        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'resident_number' => fake()->unique()->numerify('RES-T######'),
            'last_name' => $last,
            'first_name' => $first,
            'full_name' => fn (array $attributes) => NameNormalizer::fullName($attributes['last_name'], $attributes['first_name'], $attributes['middle_name'] ?? null, $attributes['name_extension'] ?? null),
            'birthdate' => fake()->dateTimeBetween('-'.self::MAXIMUM_AGE.' years', '-'.self::MINIMUM_AGE.' years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'civil_status' => CivilStatus::Single,
        ];
    }

    public function named(string $lastName, string $firstName, ?string $middleName = null): static
    {
        return $this->state(fn () => [
            'last_name' => $lastName,
            'first_name' => $firstName,
            'middle_name' => $middleName,
        ]);
    }

    public function deceased(): static
    {
        return $this->state(fn () => ['is_deceased' => true]);
    }
}
