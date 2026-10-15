<?php

namespace App\Domain\Forms;

final class PlaceholderCatalog
{
    public const string RESIDENT = 'resident';

    public const string BARANGAY = 'barangay';

    public const string SIGNATORY = 'signatory';

    public const string DOCUMENT = 'document';

    public const array RESIDENT_KEYS = [
        'full_name', 'first_name', 'middle_name', 'last_name', 'name_extension', 'title', 'resident_number',
        'address', 'street_address', 'purok', 'age', 'birthdate', 'birthdate_long', 'birthplace', 'gender',
        'civil_status', 'nationality', 'religion',
    ];

    public const array BARANGAY_KEYS = ['name', 'municipality', 'province', 'locality', 'telephone'];

    public const array SIGNATORY_KEYS = [
        'punong_barangay', 'punong_barangay_title', 'secretary', 'secretary_title', 'treasurer', 'treasurer_title',
    ];

    public const array DOCUMENT_KEYS = [
        'number', 'or_number', 'amount_paid', 'date', 'given_day', 'given_month', 'given_year', 'given_date',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            self::RESIDENT => self::RESIDENT_KEYS,
            self::BARANGAY => self::BARANGAY_KEYS,
            self::SIGNATORY => self::SIGNATORY_KEYS,
            self::DOCUMENT => self::DOCUMENT_KEYS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function tokens(): array
    {
        $tokens = [];
        foreach (self::groups() as $namespace => $keys) {
            foreach ($keys as $key) {
                $tokens[] = Placeholder::token($namespace, $key);
            }
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    public static function snapshotTokens(): array
    {
        return array_values(array_filter(
            self::tokens(),
            fn (string $token): bool => ! str_starts_with($token, self::DOCUMENT.'.'),
        ));
    }
}
