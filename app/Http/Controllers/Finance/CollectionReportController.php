<?php

namespace App\Http\Controllers\Finance;

use App\Enums\CollectionSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CollectionReportRequest;
use App\Http\Support\EnumOptions;
use App\Queries\ConsolidatedCollectionQuery;
use App\Queries\DocumentPrintContext;
use App\Services\Finance\CollectionCsvExporter;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionReportController extends Controller
{
    private const string FILE_NAME = 'collections-%s-%s.csv';

    private const string FILE_DATE_FORMAT = 'Ymd';

    private const string CONTENT_TYPE = 'text/csv; charset=UTF-8';

    private const string OUTPUT_STREAM = 'php://output';

    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    public function index(CollectionReportRequest $request, ConsolidatedCollectionQuery $query): Response
    {
        $filters = $request->filters(CarbonImmutable::today());
        $page = $query->paginate($filters, $request->perPage());

        return Inertia::render('collections/Report', [
            'rows' => ['data' => array_values($page->items()), 'meta' => $this->meta($page)],
            'totals' => $query->totals($filters),
            'bySource' => $query->bySource($filters),
            'byType' => $query->byType($filters),
            'filters' => $filters->toArray(),
            'sources' => EnumOptions::of(CollectionSource::cases()),
        ]);
    }

    public function print(CollectionReportRequest $request, ConsolidatedCollectionQuery $query, DocumentPrintContext $context): Response
    {
        $filters = $request->filters(CarbonImmutable::today());

        return Inertia::render('collections/ReportPrint', [
            'header' => $context->header(),
            'rows' => $query->forPrint($filters),
            'limit' => ConsolidatedCollectionQuery::PRINT_LIMIT,
            'totals' => $query->totals($filters),
            'bySource' => $query->bySource($filters),
            'byType' => $query->byType($filters),
            'filters' => $filters->toArray(),
            'printedAt' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }

    public function export(CollectionReportRequest $request, CollectionCsvExporter $exporter, CurrentBarangay $currentBarangay): StreamedResponse
    {
        $barangay = $currentBarangay->require();
        $filters = $request->filters(CarbonImmutable::today());
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
        }, sprintf(self::FILE_NAME, $filters->from->format(self::FILE_DATE_FORMAT), $filters->to->format(self::FILE_DATE_FORMAT)), ['Content-Type' => self::CONTENT_TYPE]);
    }

    /**
     * @param  LengthAwarePaginator<int, array<string, mixed>>  $page
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    private function meta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }
}
