<?php

namespace App\Http\Controllers\IdCards;

use App\Enums\IssuedDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentListRequest;
use App\Http\Requests\IdCards\IdCardBatchRequest;
use App\Http\Requests\IdCards\IdCardCancelRequest;
use App\Http\Requests\IdCards\IdCardRequest;
use App\Http\Resources\IdCards\IdCardResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\IdCard;
use App\Queries\IdCardLedgerQuery;
use App\Queries\IdCardPrintQuery;
use App\Queries\ResidentPickerQuery;
use App\Services\IdCards\IdCardService;
use App\Support\RouteNames;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IdCardController extends Controller
{
    public function __construct(private readonly IdCardService $cards) {}

    public function index(DocumentListRequest $request, IdCardLedgerQuery $query): Response
    {
        $filters = $request->filters();

        return Inertia::render('id-cards/Index', [
            'cards' => Paginated::from($query->paginate($filters, $request->perPage()), IdCardResource::class),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(IssuedDocumentStatus::cases()),
        ]);
    }

    public function printLedger(DocumentListRequest $request, IdCardLedgerQuery $query, CurrentBarangay $currentBarangay): Response
    {
        $filters = $request->filters();

        return Inertia::render('id-cards/LedgerPrint', [
            'cards' => array_values(IdCardResource::collection($query->forPrint($filters))->resolve()),
            'total' => $query->total($filters),
            'limit' => IdCardLedgerQuery::PRINT_LIMIT,
            'filters' => $filters->toArray(),
            'barangay' => $currentBarangay->require()->name,
            'printedAt' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }

    public function create(Request $request, ResidentPickerQuery $residents): Response
    {
        return Inertia::render('id-cards/Form', [
            'card' => null,
            'resident' => $residents->option($request->filled('resident_id') ? $request->integer('resident_id') : null),
        ]);
    }

    public function store(IdCardRequest $request): RedirectResponse
    {
        $card = $this->cards->issue($request->residentId(), $request->details(), $this->actor($request));
        Toast::success('messages.id_card_issued', ['number' => $card->card_number]);

        return to_route(RouteNames::ID_CARDS_INDEX);
    }

    public function edit(IdCard $idCard, ResidentPickerQuery $residents): Response
    {
        $idCard->load('resident:id,full_name,resident_number,deleted_at');

        return Inertia::render('id-cards/Form', [
            'card' => (new IdCardResource($idCard))->resolve(),
            'resident' => $residents->option($idCard->resident_id),
        ]);
    }

    public function update(IdCardRequest $request, IdCard $idCard): RedirectResponse
    {
        $this->cards->update($idCard, $request->details(), $this->actor($request));
        Toast::success('messages.id_card_updated');

        return to_route(RouteNames::ID_CARDS_INDEX);
    }

    public function print(Request $request, IdCard $idCard, IdCardPrintQuery $print): Response
    {
        $card = $this->cards->recordPrint($idCard, $this->actor($request));

        return Inertia::render('id-cards/CardPrint', $print->data($card));
    }

    public function release(IdCardBatchRequest $request): RedirectResponse
    {
        $count = $this->cards->release($request->ids(), $this->actor($request));
        Toast::success('messages.id_cards_released', ['count' => $count]);

        return back();
    }

    public function cancel(IdCardCancelRequest $request): RedirectResponse
    {
        $count = $this->cards->cancel($request->ids(), $request->reason(), $this->actor($request));
        Toast::success('messages.id_cards_cancelled', ['count' => $count]);

        return back();
    }
}
