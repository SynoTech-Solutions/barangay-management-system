<?php

namespace App\Http\Controllers\Sms;

use App\Domain\Residents\PhilippineMobileNumber;
use App\Domain\Sms\SmsSegments;
use App\Enums\FeatureFlag;
use App\Enums\SmsSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sms\SmsComposeRequest;
use App\Http\Support\Toast;
use App\Models\SmsMessage;
use App\Queries\ResidentPickerQuery;
use App\Queries\SmsTemplateQuery;
use App\Services\Features\FeatureFlags;
use App\Services\Sms\SmsOutbox;
use App\Services\Sms\SmsRecipientResolver;
use App\Support\Config\SmsSettings;
use App\Support\RouteNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsComposeController extends Controller
{
    public function create(Request $request, ResidentPickerQuery $residents, SmsTemplateQuery $templates, FeatureFlags $features, SmsSettings $settings): Response
    {
        $residentId = $request->integer('resident_id') ?: null;
        $replyTo = $request->integer('reply_to') ?: null;
        $reply = $replyTo === null ? null : SmsMessage::query()->find($replyTo);

        return Inertia::render('sms/Compose', [
            'prefill' => [
                'resident' => $residents->option($residentId),
                'numbers' => $reply?->resident_id === null && $reply?->recipient !== null
                    ? [PhilippineMobileNumber::normalize($reply->recipient)]
                    : [],
                'reply_resident' => $reply?->resident_id === null ? null : $residents->option($reply->resident_id),
            ],
            'templates' => $features->enabled(FeatureFlag::SmsTemplates) ? $templates->options() : [],
            'limits' => self::limits($settings),
        ]);
    }

    public function store(SmsComposeRequest $request, SmsRecipientResolver $resolver, SmsOutbox $outbox): RedirectResponse
    {
        $recipients = $resolver->fromResidentIds($request->residentIds())->merge($resolver->fromNumbers($request->numbers()));
        $batch = $outbox->queue($recipients, $request->body(), SmsSource::Compose, null, $this->actor($request));

        Toast::success('messages.sms_queued', ['count' => $batch->recipient_count, 'skipped' => $batch->skipped_count]);

        return to_route(RouteNames::SMS_INDEX);
    }

    /**
     * @return array{max_segments: int, max_recipients: int, gsm_single: int, gsm_multi: int, ucs2_single: int, ucs2_multi: int}
     */
    public static function limits(SmsSettings $settings): array
    {
        return [
            'max_segments' => SmsSegments::MAX_SEGMENTS,
            'max_recipients' => $settings->maxRecipients,
            'gsm_single' => SmsSegments::GSM_SINGLE,
            'gsm_multi' => SmsSegments::GSM_MULTI,
            'ucs2_single' => SmsSegments::UCS2_SINGLE,
            'ucs2_multi' => SmsSegments::UCS2_MULTI,
        ];
    }
}
