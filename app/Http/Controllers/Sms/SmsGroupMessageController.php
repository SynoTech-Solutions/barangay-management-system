<?php

namespace App\Http\Controllers\Sms;

use App\Enums\FeatureFlag;
use App\Enums\SmsSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sms\SmsBodyRequest;
use App\Http\Resources\Sms\SmsGroupResource;
use App\Http\Support\Toast;
use App\Models\SmsGroup;
use App\Queries\SmsTemplateQuery;
use App\Services\Features\FeatureFlags;
use App\Services\Sms\SmsGroupService;
use App\Services\Sms\SmsOutbox;
use App\Services\Sms\SmsRecipientResolver;
use App\Support\Config\SmsSettings;
use App\Support\RouteNames;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SmsGroupMessageController extends Controller
{
    public function create(SmsGroup $group, SmsRecipientResolver $resolver, SmsTemplateQuery $templates, FeatureFlags $features, SmsSettings $settings): Response
    {
        $group->loadCount('members');

        return Inertia::render('sms/groups/Message', [
            'group' => (new SmsGroupResource($group))->resolve(),
            'summary' => $resolver->fromGroup($group)->summary(),
            'templates' => $features->enabled(FeatureFlag::SmsTemplates) ? $templates->options() : [],
            'limits' => SmsComposeController::limits($settings),
        ]);
    }

    public function store(SmsBodyRequest $request, SmsGroup $group, SmsRecipientResolver $resolver, SmsGroupService $groups, SmsOutbox $outbox): RedirectResponse
    {
        $groups->ensureActive($group);
        $batch = $outbox->queue($resolver->fromGroup($group), $request->body(), SmsSource::Group, $group, $this->actor($request));

        Toast::success('messages.sms_queued', ['count' => $batch->recipient_count, 'skipped' => $batch->skipped_count]);

        return to_route(RouteNames::SMS_INDEX);
    }
}
