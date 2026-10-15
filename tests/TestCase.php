<?php

namespace Tests;

use App\Enums\SystemRole;
use App\Models\Barangay;
use App\Models\User;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function barangayUser(SystemRole $role, ?Barangay $barangay = null): User
    {
        $barangay ??= Barangay::factory()->create();

        return User::factory()->forBarangay($barangay)->withRole($role)->create();
    }

    protected function centralAdmin(): User
    {
        return User::factory()->withRole(SystemRole::CentralAdmin)->create();
    }

    protected function inBarangay(Barangay $barangay): void
    {
        app(CurrentBarangay::class)->set($barangay);
    }
}
