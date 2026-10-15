<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\ResidentWorkHistoryRequest;
use App\Http\Support\Toast;
use App\Models\Resident;
use App\Models\ResidentWorkHistory;
use App\Services\Residents\ResidentDetailRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResidentWorkHistoryController extends Controller
{
    public function __construct(private readonly ResidentDetailRecords $records) {}

    public function store(ResidentWorkHistoryRequest $request, Resident $resident): RedirectResponse
    {
        $this->records->create($resident, new ResidentWorkHistory, $request->work(), $this->actor($request));
        Toast::success('messages.work_history_saved');

        return back();
    }

    public function update(ResidentWorkHistoryRequest $request, Resident $resident, ResidentWorkHistory $workHistory): RedirectResponse
    {
        $this->records->update($workHistory, $request->work(), $this->actor($request));
        Toast::success('messages.work_history_saved');

        return back();
    }

    public function destroy(Request $request, Resident $resident, ResidentWorkHistory $workHistory): RedirectResponse
    {
        $this->records->remove($workHistory, $this->actor($request));
        Toast::success('messages.work_history_removed');

        return back();
    }
}
