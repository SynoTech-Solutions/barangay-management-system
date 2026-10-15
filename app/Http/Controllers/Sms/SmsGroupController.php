<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sms\SmsGroupRequest;
use App\Http\Resources\Sms\SmsGroupMemberResource;
use App\Http\Resources\Sms\SmsGroupResource;
use App\Http\Support\Toast;
use App\Models\SmsGroup;
use App\Queries\SmsGroupQuery;
use App\Services\Sms\SmsGroupService;
use App\Support\RouteNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsGroupController extends Controller
{
    public function __construct(private readonly SmsGroupService $groups) {}

    public function index(SmsGroupQuery $groups): Response
    {
        return Inertia::render('sms/groups/Index', [
            'groups' => array_values(SmsGroupResource::collection($groups->all())->resolve()),
        ]);
    }

    public function store(SmsGroupRequest $request): RedirectResponse
    {
        $group = $this->groups->create($request->groupName(), $this->actor($request));
        Toast::success('messages.sms_group_created', ['name' => $group->name]);

        return to_route(RouteNames::SMS_GROUPS_SHOW, $group);
    }

    public function show(SmsGroup $group, SmsGroupQuery $groups): Response
    {
        $group->loadCount('members');

        return Inertia::render('sms/groups/Show', [
            'group' => (new SmsGroupResource($group))->resolve(),
            'members' => array_values(SmsGroupMemberResource::collection($groups->members($group))->resolve()),
        ]);
    }

    public function update(SmsGroupRequest $request, SmsGroup $group): RedirectResponse
    {
        $this->groups->rename($group, $request->groupName(), $this->actor($request));
        Toast::success('messages.sms_group_renamed');

        return back();
    }

    public function deactivate(Request $request, SmsGroup $group): RedirectResponse
    {
        $this->groups->setActive($group, false, $this->actor($request));
        Toast::success('messages.record_deactivated');

        return back();
    }

    public function reactivate(Request $request, SmsGroup $group): RedirectResponse
    {
        $this->groups->setActive($group, true, $this->actor($request));
        Toast::success('messages.record_reactivated');

        return back();
    }
}
