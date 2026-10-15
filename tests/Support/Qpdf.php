<?php

namespace Tests\Support;

final class Qpdf
{
    private const array CANDIDATES = ['/usr/bin/qpdf', '/usr/local/bin/qpdf'];

    public static function binary(): ?string
    {
        foreach (self::CANDIDATES as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $arguments
     */
    public static function run(string $pdf, array $arguments): ?string
    {
        $binary = self::binary();
        if ($binary === null) {
            return null;
        }

        $input = (string) tempnam(sys_get_temp_dir(), 'bms-test-in');
        $output = (string) tempnam(sys_get_temp_dir(), 'bms-test-out');
        file_put_contents($input, $pdf);
        $command = implode(' ', array_map('escapeshellarg', [$binary, ...$arguments, $input, $output]));
        exec($command.' 2>/dev/null', result_code: $code);
        $result = in_array($code, [0, 3], true) ? (string) file_get_contents($output) : null;
        @unlink($input);
        @unlink($output);

        return $result;
    }
}
