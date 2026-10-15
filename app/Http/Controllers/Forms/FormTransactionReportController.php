<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\FormTransactionListRequest;
use App\Http\Resources\Forms\FormTransactionResource;
use App\Http\Support\CsvDownload;
use App\Models\FormTransaction;
use App\Queries\DocumentPrintContext;
use App\Queries\FormTransactionListQuery;
use App\Services\Forms\FormTransactionCsvExporter;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormTransactionReportController extends Controller
{
    private const string FILE_PREFIX = 'forms';

    public function export(FormTransactionListRequest $request, FormTransactionCsvExporter $exporter, CurrentBarangay $currentBarangay): StreamedResponse
    {
        $barangay = $currentBarangay->require();
        $filters = $request->filters();
        $exporter->recordExport($filters, $barangay->id, $this->actor($request));

        return CsvDownload::stream(self::FILE_PREFIX, $exporter->header($filters), fn () => $exporter->rows($filters), $barangay, $currentBarangay);
    }

    public function print(FormTransactionListRequest $request, FormTransactionListQuery $query, DocumentPrintContext $context): Response
    {
        $filters = $request->filters();
        $columns = $query->fieldColumns($filters);

        return Inertia::render('forms/transactions/ListPrint', [
            'rows' => array_values($query->forPrint($filters)->map(fn (FormTransaction $transaction): array => [
                ...(new FormTransactionResource($transaction))->resolve(),
                'field_values' => $query->fieldValues($transaction, $columns),
            ])->all()),
            'fieldColumns' => $columns,
            'count' => $query->count($filters),
            'printedAt' => CarbonImmutable::now()->toIso8601String(),
            'total' => $query->total($filters),
            'limit' => FormTransactionListQuery::PRINT_LIMIT,
            'filters' => $filters->toArray(),
            'header' => $context->header(),
        ]);
    }
}
