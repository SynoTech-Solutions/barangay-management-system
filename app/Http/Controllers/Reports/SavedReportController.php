<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\ReportDefinition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SavedReportRequest;
use App\Http\Requests\Reports\SavedReportRunRequest;
use App\Http\Resources\Reports\SavedReportResource;
use App\Http\Support\CsvDownload;
use App\Http\Support\Toast;
use App\Models\SavedReport;
use App\Queries\SavedReportCatalogQuery;
use App\Queries\SavedReportQuery;
use App\Services\Reports\SavedReportAccess;
use App\Services\Reports\SavedReportCsvExporter;
use App\Services\Reports\SavedReportService;
use App\Support\RouteNames;
use App\Tenancy\CurrentBarangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SavedReportController extends Controller
{
    private const string FILE_PREFIX = 'report';

    public function __construct(
        private readonly SavedReportService $reports,
        private readonly SavedReportAccess $access,
    ) {}

    public function index(SavedReportCatalogQuery $catalog): Response
    {
        return Inertia::render('reports/saved/Index', [
            'reports' => $catalog->active(),
        ]);
    }

    public function create(Request $request, SavedReportCatalogQuery $catalog): Response
    {
        return Inertia::render('reports/saved/Form', [
            'report' => null,
            'catalog' => $catalog->forUser($this->actor($request)),
        ]);
    }

    public function store(SavedReportRequest $request): RedirectResponse
    {
        $report = $this->reports->save(null, $request->name(), $request->definition(), $this->actor($request));
        Toast::success('messages.saved_report_saved', ['name' => $report->name]);

        return to_route(RouteNames::SAVED_REPORTS_SHOW, $report);
    }

    public function show(SavedReportRunRequest $request, SavedReport $report, SavedReportQuery $query): Response
    {
        $this->ensureActive($report);
        $definition = $this->access->visible($this->actor($request), ReportDefinition::fromArray($report->definition));
        $page = $query->paginate($definition, $request->perPage());

        return Inertia::render('reports/saved/Show', [
            'report' => (new SavedReportResource($report))->resolve(),
            'columns' => array_map(fn (string $column): array => ['key' => $column, 'label' => (string) __("reports.saved.columns.{$column}")], $query->headers($definition)),
            'rows' => [
                'data' => array_values($page->items()),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }

    public function edit(Request $request, SavedReport $report, SavedReportCatalogQuery $catalog): Response
    {
        $this->ensureActive($report);

        return Inertia::render('reports/saved/Form', [
            'report' => (new SavedReportResource($report))->resolve(),
            'catalog' => $catalog->forUser($this->actor($request)),
        ]);
    }

    public function update(SavedReportRequest $request, SavedReport $report): RedirectResponse
    {
        $this->ensureActive($report);
        $this->reports->save($report, $request->name(), $request->definition(), $this->actor($request));
        Toast::success('messages.saved_report_saved', ['name' => $request->name()]);

        return to_route(RouteNames::SAVED_REPORTS_SHOW, $report);
    }

    public function deactivate(Request $request, SavedReport $report): RedirectResponse
    {
        $this->ensureActive($report);
        $this->reports->deactivate($report, $this->actor($request));
        Toast::success('messages.saved_report_deactivated');

        return to_route(RouteNames::SAVED_REPORTS_INDEX);
    }

    public function export(Request $request, SavedReport $report, SavedReportCsvExporter $exporter, CurrentBarangay $currentBarangay): StreamedResponse
    {
        $this->ensureActive($report);
        $actor = $this->actor($request);
        $definition = $this->access->visible($actor, ReportDefinition::fromArray($report->definition));
        $exporter->recordExport($report, $definition, $actor);

        return CsvDownload::stream(self::FILE_PREFIX, $exporter->header($definition), fn () => $exporter->rows($definition), $currentBarangay->require(), $currentBarangay);
    }

    private function ensureActive(SavedReport $report): void
    {
        abort_unless($report->is_active, SymfonyResponse::HTTP_NOT_FOUND);
    }
}
