<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\ResidentEducationRequest;
use App\Http\Support\Toast;
use App\Models\Resident;
use App\Models\ResidentEducation;
use App\Services\Residents\ResidentDetailRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResidentEducationController extends Controller
{
    public function __construct(private readonly ResidentDetailRecords $records) {}

    public function store(ResidentEducationRequest $request, Resident $resident): RedirectResponse
    {
        $this->records->create($resident, new ResidentEducation, $request->education(), $this->actor($request));
        Toast::success('messages.education_saved');

        return back();
    }

    public function update(ResidentEducationRequest $request, Resident $resident, ResidentEducation $education): RedirectResponse
    {
        $this->records->update($education, $request->education(), $this->actor($request));
        Toast::success('messages.education_saved');

        return back();
    }

    public function destroy(Request $request, Resident $resident, ResidentEducation $education): RedirectResponse
    {
        $this->records->remove($education, $this->actor($request));
        Toast::success('messages.education_removed');

        return back();
    }
}
