<?php

namespace App\Http\Support;

use App\Domain\Reports\CsvCell;
use App\Models\Barangay;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvDownload
{
    private const string FILE_DATE_FORMAT = 'Ymd-His';

    private const string CONTENT_TYPE = 'text/csv; charset=UTF-8';

    private const string OUTPUT_STREAM = 'php://output';

    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    /**
     * @param  list<string>  $header
     * @param  Closure(): iterable<list<string>>  $rows
     */
    public static function stream(string $prefix, array $header, Closure $rows, Barangay $barangay, CurrentBarangay $currentBarangay): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows, $barangay, $currentBarangay): void {
            $currentBarangay->runAs($barangay, function () use ($header, $rows): void {
                $output = fopen(self::OUTPUT_STREAM, 'w');
                if ($output === false) {
                    return;
                }

                fwrite($output, self::BYTE_ORDER_MARK);
                fputcsv($output, $header, escape: '');
                foreach ($rows() as $row) {
                    fputcsv($output, array_map(CsvCell::safe(...), $row), escape: '');
                }
                fclose($output);
            });
        }, $prefix.'-'.CarbonImmutable::now()->format(self::FILE_DATE_FORMAT).'.csv', ['Content-Type' => self::CONTENT_TYPE]);
    }
}
