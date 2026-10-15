<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\BulkPurokRequest;
use App\Http\Support\Toast;
use App\Services\Residents\ResidentBulkEditor;
use Illuminate\Http\RedirectResponse;

class ResidentBulkPurokController extends Controller
{
    public function __invoke(BulkPurokRequest $request, ResidentBulkEditor $editor): RedirectResponse
    {
        $changed = $editor->changePurok($request->residentIds(), $request->purokId(), $this->actor($request));

        Toast::success('messages.residents_purok_changed', ['count' => $changed]);

        return back();
    }
}
