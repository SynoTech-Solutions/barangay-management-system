<?php

namespace App\Support;

use App\Enums\SignatoryOffice;

final class StoragePaths
{
    public const string PRIVATE_DISK = 'local';

    public static function signature(int $barangayId, SignatoryOffice $office, string $extension): string
    {
        return "barangays/{$barangayId}/signatures/{$office->value}.{$extension}";
    }

    public static function residentPhoto(int $barangayId, int $residentId, string $token, string $extension): string
    {
        return "barangays/{$barangayId}/residents/{$residentId}/photo-{$token}.{$extension}";
    }

    public static function blotterPhoto(int $barangayId, int $blotterId, string $token, string $extension): string
    {
        return "barangays/{$barangayId}/blotters/{$blotterId}/photo-{$token}.{$extension}";
    }

    public static function formTemplatePdf(int $barangayId, string $token): string
    {
        return "barangays/{$barangayId}/form-templates/{$token}.pdf";
    }

    public static function importProblems(int $barangayId, string $stamp, string $token): string
    {
        return "barangays/{$barangayId}/imports/{$stamp}-{$token}-problems.csv";
    }

    public static function barangayLogo(int $barangayId, string $extension): string
    {
        return "barangays/{$barangayId}/branding/logo.{$extension}";
    }
}
