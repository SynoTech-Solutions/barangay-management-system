<?php

namespace App\Http\Controllers\Residents;

use App\Enums\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\ResidentListRequest;
use App\Services\Features\FeatureFlags;
use App\Services\Residents\ResidentCsvExporter;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResidentExportController extends Controller
{
    private const string FILE_NAME = 'residents-%s.csv';

    private const string FILE_DATE_FORMAT = 'Ymd-His';

    private const string CONTENT_TYPE = 'text/csv; charset=UTF-8';

    private const string OUTPUT_STREAM = 'php://output';

    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    public function __invoke(
        ResidentListRequest $request,
        ResidentCsvExporter $exporter,
        CurrentBarangay $currentBarangay,
        FeatureFlags $features,
    ): StreamedResponse {
        $barangay = $currentBarangay->require();
        $filters = $request->filters(CarbonImmutable::today(), $features->enabled(FeatureFlag::SectorTagging));
        $exporter->recordExport($filters, $barangay->id, $this->actor($request));

        return response()->streamDownload(function () use ($exporter, $filters, $barangay, $currentBarangay): void {
            $currentBarangay->runAs($barangay, function () use ($exporter, $filters): void {
                $output = fopen(self::OUTPUT_STREAM, 'w');
                if ($output === false) {
                    return;
                }

                fwrite($output, self::BYTE_ORDER_MARK);
                fputcsv($output, $exporter->header(), escape: '');
                foreach ($exporter->rows($filters) as $row) {
                    fputcsv($output, $row, escape: '');
                }
                fclose($output);
            });
        }, sprintf(self::FILE_NAME, CarbonImmutable::now()->format(self::FILE_DATE_FORMAT)), ['Content-Type' => self::CONTENT_TYPE]);
    }
}
