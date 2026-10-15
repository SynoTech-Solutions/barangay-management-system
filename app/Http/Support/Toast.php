<?php

namespace App\Http\Support;

use App\Enums\ToastType;
use Inertia\Inertia;

final class Toast
{
    private const string KEY = 'toast';

    /**
     * @param  array<string, scalar>  $replace
     */
    public static function success(string $messageKey, array $replace = []): void
    {
        self::send(ToastType::Success, __($messageKey, array_map('strval', $replace)));
    }

    public static function error(string $message): void
    {
        self::send(ToastType::Error, $message);
    }

    private static function send(ToastType $type, string $message): void
    {
        Inertia::flash(self::KEY, ['type' => $type->value, 'message' => $message]);
    }
}
