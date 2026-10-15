<?php

namespace Tests\Unit\Domain;

use App\Domain\Audit\AuditRedactor;
use PHPUnit\Framework\TestCase;

class AuditRedactorTest extends TestCase
{
    public function test_sensitive_keys_are_redacted_at_any_depth(): void
    {
        $payload = AuditRedactor::payload(
            ['password' => 'secret', 'name' => 'A'],
            ['nested' => ['two_factor_secret' => 'x', 'keep' => 1]],
        );

        $this->assertSame(AuditRedactor::PAYLOAD_VERSION, $payload['v']);
        $this->assertSame(AuditRedactor::REDACTED, $payload['before']['password']);
        $this->assertSame('A', $payload['before']['name']);
        $this->assertSame(AuditRedactor::REDACTED, $payload['after']['nested']['two_factor_secret']);
        $this->assertSame(1, $payload['after']['nested']['keep']);
    }

    public function test_government_id_numbers_and_photo_paths_are_redacted(): void
    {
        $payload = AuditRedactor::payload(null, [
            'national_id' => '1234-5678',
            'tin' => '123-456-789',
            'sss_gsis' => '01-2345678-9',
            'pagibig' => '1234-5678-9012',
            'philhealth' => '12-345678901-2',
            'drivers_license' => 'N01-23-456789',
            'voter_id' => '1234-5678A',
            'photo_path' => 'barangays/1/residents/1/photo.jpg',
            'last_name' => 'Dela Cruz',
        ]);

        foreach (['national_id', 'tin', 'sss_gsis', 'pagibig', 'philhealth', 'drivers_license', 'voter_id', 'photo_path'] as $key) {
            $this->assertSame(AuditRedactor::REDACTED, $payload['after'][$key], $key);
        }
        $this->assertSame('Dela Cruz', $payload['after']['last_name']);
    }

    public function test_changed_only_keeps_differences(): void
    {
        [$before, $after] = AuditRedactor::changedOnly(['a' => 1, 'b' => 2], ['a' => 1, 'b' => 3, 'c' => 4]);

        $this->assertSame(['b' => 2, 'c' => null], $before);
        $this->assertSame(['b' => 3, 'c' => 4], $after);
    }
}
