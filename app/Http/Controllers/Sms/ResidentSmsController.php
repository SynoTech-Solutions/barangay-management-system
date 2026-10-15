<?php

namespace App\Http\Controllers\Sms;

use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Enums\SmsSource;
use App\Exceptions\BmsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sms\ResidentSmsGroupRequest;
use App\Http\Requests\Sms\ResidentSmsSelectionRequest;
use App\Http\Requests\Sms\ResidentSmsSendRequest;
use App\Http\Support\Toast;
use App\Models\SmsGroup;
use App\Queries\ResidentRecipientQuery;
use App\Queries\SmsGroupQuery;
use App\Queries\SmsTemplateQuery;
use App\Services\Features\FeatureFlags;
use App\Services\Sms\RecipientSet;
use App\Services\Sms\SmsGroupService;
use App\Services\Sms\SmsOutbox;
use App\Support\Config\SmsSettings;
use App\Support\RouteNames;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ResidentSmsController extends Controller
{
    private const string MODE_SEND = 'send';

    private const string MODE_GROUP = 'group';

    public function __construct(
        private readonly ResidentRecipientQuery $recipients,
        private readonly FeatureFlags $features,
        private readonly SmsSettings $settings,
        private readonly SmsOutbox $outbox,
    ) {}

    public function createSend(ResidentSmsSelectionRequest $request, SmsTemplateQuery $templates): Response
    {
        return $this->preview($request, self::MODE_SEND, [
            'templates' => $this->features->enabled(FeatureFlag::SmsTemplates) ? $templates->options() : [],
            'groups' => [],
        ]);
    }

    public function storeSend(ResidentSmsSendRequest $request): RedirectResponse
    {
        $recipients = $this->confirmed($request, $request->confirmedCount());
        $batch = $this->outbox->queue($recipients, $request->body(), SmsSource::ResidentList, null, $this->actor($request));

        Toast::success('messages.sms_queued', ['count' => $batch->recipient_count, 'skipped' => $batch->skipped_count]);

        return to_route(RouteNames::SMS_INDEX);
    }

    public function createGroup(ResidentSmsSelectionRequest $request, SmsGroupQuery $groups): Response
    {
        return $this->preview($request, self::MODE_GROUP, [
            'templates' => [],
            'groups' => $groups->activeOptions(),
        ]);
    }

    public function storeGroup(ResidentSmsGroupRequest $request, SmsGroupService $groups): RedirectResponse
    {
        $group = SmsGroup::query()->findOrFail($request->groupId());
        $groups->ensureActive($group);
        $recipients = $this->confirmed($request, $request->confirmedCount());
        $result = $groups->addResidents($group, $recipients, $this->actor($request));

        Toast::success('messages.sms_group_members_added', $result);

        return to_route(RouteNames::SMS_GROUPS_SHOW, $group);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function preview(ResidentSmsSelectionRequest $request, string $mode, array $extra): Response
    {
        $recipients = $this->resolve($request);

        return Inertia::render('sms/FromResidents', [
            'mode' => $mode,
            'selection' => $request->selection(),
            'summary' => [...$recipients->summary(), 'max' => $this->settings->maxRecipients, 'over_cap' => $recipients->count() > $this->settings->maxRecipients],
            'limits' => SmsComposeController::limits($this->settings),
            ...$extra,
        ]);
    }

    private function confirmed(ResidentSmsSelectionRequest $request, int $confirmedCount): RecipientSet
    {
        $recipients = $this->resolve($request);
        $this->outbox->ensureSendable($recipients);

        if ($recipients->count() !== $confirmedCount) {
            throw BmsException::of(ErrorCode::SmsRecipientsChanged, ['count' => $recipients->count()]);
        }

        return $recipients;
    }

    private function resolve(ResidentSmsSelectionRequest $request): RecipientSet
    {
        return $this->recipients->resolve(
            $request->isFiltered() ? $request->filters(CarbonImmutable::today(), $this->features->enabled(FeatureFlag::SectorTagging)) : null,
            $request->residentIds(),
        );
    }
}
