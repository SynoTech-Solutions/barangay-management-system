<?php

namespace App\Http\Controllers\Forms;

use App\Enums\FormTemplateKind;
use App\Enums\IssuedDocumentStatus;
use App\Enums\LookupType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\FormTransactionBatchRequest;
use App\Http\Requests\Forms\FormTransactionCancelRequest;
use App\Http\Requests\Forms\FormTransactionListRequest;
use App\Http\Requests\Forms\FormTransactionRequest;
use App\Http\Resources\Forms\FormTransactionResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\FormTransaction;
use App\Queries\FormDocumentQuery;
use App\Queries\FormOptionsQuery;
use App\Queries\FormTemplateQuery;
use App\Queries\FormTransactionListQuery;
use App\Queries\ResidentPickerQuery;
use App\Services\Forms\FormTransactionService;
use App\Support\RouteNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FormTransactionController extends Controller
{
    public function __construct(
        private readonly FormTransactionService $transactions,
        private readonly FormDocumentQuery $documents,
    ) {}

    public function index(FormTransactionListRequest $request, FormTransactionListQuery $query, FormOptionsQuery $options, FormTemplateQuery $templates): Response
    {
        $filters = $request->filters();

        return Inertia::render('forms/transactions/Index', [
            'transactions' => Paginated::from($query->paginate($filters, $request->perPage()), FormTransactionResource::class),
            'total' => $query->total($filters),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(IssuedDocumentStatus::cases()),
            'types' => $options->lookups([LookupType::FormTemplateType])[LookupType::FormTemplateType->value],
            'templates' => $templates->templateOptions(),
        ]);
    }

    public function create(Request $request, FormTemplateQuery $templates, ResidentPickerQuery $residents): Response
    {
        return Inertia::render('forms/transactions/Form', [
            'transaction' => null,
            'values' => (object) [],
            'templates' => $templates->issuable(),
            'resident' => $residents->option($request->filled('resident_id') ? $request->integer('resident_id') : null),
            'selectedTemplateId' => $request->filled('template_id') ? $request->integer('template_id') : null,
        ]);
    }

    public function store(FormTransactionRequest $request): RedirectResponse|SymfonyResponse
    {
        $transaction = $this->transactions->issue($request->templateId(), $request->residentId(), $request->values(), $request->orNumber(), $request->amount(), $this->actor($request));
        Toast::success('messages.form_transaction_issued', ['number' => $transaction->transaction_number]);

        if (! $this->transactions->autoPrint()) {
            return to_route(RouteNames::FORM_TRANSACTIONS_INDEX);
        }

        return $transaction->template?->kind === FormTemplateKind::Pdf
            ? Inertia::location(route(RouteNames::FORM_TRANSACTIONS_PRINT, $transaction))
            : to_route(RouteNames::FORM_TRANSACTIONS_PRINT, $transaction);
    }

    public function edit(FormTransaction $transaction, FormTemplateQuery $templates, ResidentPickerQuery $residents): Response
    {
        return Inertia::render('forms/transactions/Form', [
            ...$templates->transactionEditor($transaction),
            'templates' => [],
            'resident' => $residents->option($transaction->resident_id),
            'selectedTemplateId' => $transaction->form_template_id,
        ]);
    }

    public function update(FormTransactionRequest $request, FormTransaction $transaction): RedirectResponse
    {
        $this->transactions->update($transaction, $request->values(), $request->orNumber(), $request->amount(), $this->actor($request));
        Toast::success('messages.form_transaction_updated', ['number' => $transaction->transaction_number]);

        return to_route(RouteNames::FORM_TRANSACTIONS_INDEX);
    }

    public function print(Request $request, FormTransaction $transaction): Response|HttpResponse
    {
        $transaction->loadMissing('template:id,kind,name');

        if ($transaction->template?->kind === FormTemplateKind::Pdf) {
            $contents = $this->transactions->printPdf($transaction, $this->actor($request), fn (FormTransaction $locked): string => $this->documents->pdf($locked));

            return response($contents, SymfonyResponse::HTTP_OK, [
                'Content-Type' => FormTemplatePreviewController::PDF_CONTENT_TYPE,
                'Content-Disposition' => 'inline; filename="'.$transaction->transaction_number.'.pdf"',
                'Cache-Control' => FormTemplatePreviewController::CACHE_CONTROL,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $transaction = $this->transactions->recordPrint($transaction, $this->actor($request));

        return Inertia::render('forms/transactions/Print', $this->documents->printData($transaction));
    }

    public function release(FormTransactionBatchRequest $request): RedirectResponse
    {
        $count = $this->transactions->release($request->ids(), $this->actor($request));
        Toast::success('messages.form_transactions_released', ['count' => $count]);

        return back();
    }

    public function cancel(FormTransactionCancelRequest $request): RedirectResponse
    {
        $count = $this->transactions->cancel($request->ids(), $request->reason(), $this->actor($request));
        Toast::success('messages.form_transactions_cancelled', ['count' => $count]);

        return back();
    }
}
