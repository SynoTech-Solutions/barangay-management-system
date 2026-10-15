<?php

namespace App\Http\Controllers\Forms;

use App\Enums\FormTemplateKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\FormPdfInspectRequest;
use App\Http\Requests\Forms\FormPreviewRequest;
use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use App\Queries\DocumentPrintContext;
use App\Queries\FormDocumentQuery;
use App\Services\Forms\FormTemplateService;
use App\Services\Forms\Pdf\FormPdfStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FormTemplatePreviewController extends Controller
{
    public const string PDF_CONTENT_TYPE = 'application/pdf';

    public const string CACHE_CONTROL = 'private, no-store';

    public function __construct(
        private readonly FormDocumentQuery $documents,
        private readonly FormTemplateService $templates,
    ) {}

    public function show(FormTemplate $template, FormTemplateVersion $version, DocumentPrintContext $context): Response|HttpResponse
    {
        abort_unless($version->form_template_id === $template->id, SymfonyResponse::HTTP_NOT_FOUND);
        $this->templates->ensureKindEnabled($template->kind);

        if ($template->kind === FormTemplateKind::Pdf) {
            return $this->pdf($this->documents->previewPdf($version, CarbonImmutable::now()), $template->template_number.'-v'.$version->version);
        }

        $rendered = $this->documents->previewHtml((string) $version->body, CarbonImmutable::now());

        return Inertia::render('forms/templates/PreviewPrint', [
            'template' => ['id' => $template->id, 'name' => $template->name, 'version' => $version->version],
            'html' => $rendered->html,
            'missing' => $rendered->missing,
            'header' => $context->header(),
        ]);
    }

    public function source(FormTemplate $template, FormTemplateVersion $version, FormPdfStore $pdfs): HttpResponse
    {
        abort_unless($version->form_template_id === $template->id && $version->pdf_path !== null, SymfonyResponse::HTTP_NOT_FOUND);

        return $this->pdf($pdfs->contents($version), $template->template_number.'-v'.$version->version.'-source');
    }

    public function draft(FormPreviewRequest $request): JsonResponse
    {
        $rendered = $this->documents->previewHtml($request->body(), CarbonImmutable::now());

        return response()->json(['html' => $rendered->html, 'missing' => $rendered->missing]);
    }

    public function inspect(FormPdfInspectRequest $request, FormPdfStore $pdfs): JsonResponse
    {
        $fields = $pdfs->inspect($request->pdf()->getContent());

        return response()->json(['fields' => array_map(fn ($field): array => $field->toArray(), $fields)]);
    }

    private function pdf(string $contents, string $name): HttpResponse
    {
        return response($contents, SymfonyResponse::HTTP_OK, [
            'Content-Type' => self::PDF_CONTENT_TYPE,
            'Content-Disposition' => 'inline; filename="'.$name.'.pdf"',
            'Cache-Control' => self::CACHE_CONTROL,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
