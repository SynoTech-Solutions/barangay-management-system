<?php

namespace App\Domain\Sms;

final class SmsText
{
    private const string LINE_ENDINGS = '/\r\n?/u';

    private const string CONTROL_CHARACTERS = '/[\x{0000}-\x{0009}\x{000B}-\x{001F}\x{007F}-\x{009F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u';

    public static function clean(string $text): string
    {
        $text = (string) preg_replace(self::LINE_ENDINGS, "\n", $text);

        return trim((string) preg_replace(self::CONTROL_CHARACTERS, '', $text));
    }
}
