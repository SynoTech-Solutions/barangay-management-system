<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sms\SmsGroupMemberRequest;
use App\Http\Support\Toast;
use App\Models\SmsGroup;
use App\Models\SmsGroupMember;
use App\Services\Sms\SmsGroupService;
use App\Services\Sms\SmsRecipientResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SmsGroupMemberController extends Controller
{
    public function __construct(private readonly SmsGroupService $groups) {}

    public function store(SmsGroupMemberRequest $request, SmsGroup $group, SmsRecipientResolver $resolver): RedirectResponse
    {
        $residentId = $request->residentId();

        if ($residentId !== null) {
            $result = $this->groups->addResidents($group, $resolver->fromResidentIds([$residentId]), $this->actor($request));
            Toast::success('messages.sms_group_members_added', $result);

            return back();
        }

        $this->groups->addNumber($group, (string) $request->mobileNumber(), $request->label(), $this->actor($request));
        Toast::success('messages.sms_group_members_added', ['added' => 1, 'already' => 0, 'skipped' => 0]);

        return back();
    }

    public function destroy(Request $request, SmsGroup $group, SmsGroupMember $member): RedirectResponse
    {
        $this->groups->removeMember($group, $member, $this->actor($request));
        Toast::success('messages.sms_group_member_removed');

        return back();
    }
}
