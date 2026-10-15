<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\FamilyMemberRequest;
use App\Http\Support\Toast;
use App\Models\FamilyMember;
use App\Models\Resident;
use App\Services\Residents\FamilyMemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FamilyMemberController extends Controller
{
    public function __construct(private readonly FamilyMemberService $members) {}

    public function store(FamilyMemberRequest $request, Resident $resident): RedirectResponse
    {
        $this->members->add($resident, $request->member(), $this->actor($request));
        Toast::success('messages.family_member_saved');

        return back();
    }

    public function update(FamilyMemberRequest $request, Resident $resident, FamilyMember $familyMember): RedirectResponse
    {
        $this->members->update($familyMember, $request->member(), $this->actor($request));
        Toast::success('messages.family_member_saved');

        return back();
    }

    public function destroy(Request $request, Resident $resident, FamilyMember $familyMember): RedirectResponse
    {
        $this->members->remove($familyMember, $this->actor($request));
        Toast::success('messages.family_member_removed');

        return back();
    }
}
