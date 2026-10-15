<?php

namespace App\Http\Controllers\Sms;

use App\Enums\SmsStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sms\SmsListRequest;
use App\Http\Requests\Sms\SmsMessageBatchRequest;
use App\Http\Resources\Sms\SmsMessageResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\SmsMessage;
use App\Queries\SmsOutboxQuery;
use App\Services\Sms\SmsMessageActions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SmsMessageController extends Controller
{
    public function __construct(private readonly SmsMessageActions $actions) {}

    public function index(SmsListRequest $request, SmsOutboxQuery $outbox): Response
    {
        $filters = $request->filters();

        return Inertia::render('sms/Index', [
            'messages' => Paginated::from($outbox->paginate($filters, $request->perPage()), SmsMessageResource::class),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(SmsStatus::cases()),
        ]);
    }

    public function show(SmsMessage $message): Response
    {
        $message->load(['resident:id,full_name,deleted_at', 'queuedBy:id,name', 'cancelledBy:id,name', 'batch']);

        return Inertia::render('sms/Show', [
            'message' => (new SmsMessageResource($message))->resolve(),
        ]);
    }

    public function cancel(SmsMessageBatchRequest $request): RedirectResponse
    {
        $count = $this->actions->cancel($request->ids(), $this->actor($request));
        Toast::success('messages.sms_cancelled', ['count' => $count]);

        return back();
    }

    public function retry(SmsMessageBatchRequest $request): RedirectResponse
    {
        $count = $this->actions->retry($request->ids(), $this->actor($request));
        Toast::success('messages.sms_retried', ['count' => $count]);

        return back();
    }
}
