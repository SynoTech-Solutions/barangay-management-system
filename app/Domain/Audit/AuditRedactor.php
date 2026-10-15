<?php

namespace App\Domain\Audit;

final class AuditRedactor
{
    public const int PAYLOAD_VERSION = 1;

    public const string REDACTED = '[redacted]';

    private const array SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'signature_path',
        'fingerprint',
        'photo_path',
        'national_id',
        'drivers_license',
        'tin',
        'sss_gsis',
        'pagibig',
        'philhealth',
        'voter_id',
        'narrative',
        'resolution_notes',
        'account_number',
        'body',
        'snapshot',
        'field_values',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @return array{v: int, before: array<string, mixed>|null, after: array<string, mixed>|null}
     */
    public static function payload(?array $before, ?array $after): array
    {
        return [
            'v' => self::PAYLOAD_VERSION,
            'before' => $before === null ? null : self::redact($before),
            'after' => $after === null ? null : self::redact($after),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::SENSITIVE_KEYS, true)) {
                $values[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $values[$key] = self::redact($value);
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function changedOnly(array $before, array $after): array
    {
        $changedBefore = [];
        $changedAfter = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new) {
                $changedBefore[$key] = $old;
                $changedAfter[$key] = $new;
            }
        }

        return [$changedBefore, $changedAfter];
    }
}
