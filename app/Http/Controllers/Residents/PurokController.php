<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\PurokRequest;
use App\Http\Resources\Residents\PurokResource;
use App\Http\Support\Toast;
use App\Models\Purok;
use App\Services\Residents\PurokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurokController extends Controller
{
    public function __construct(private readonly PurokService $puroks) {}

    public function index(): Response
    {
        return Inertia::render('residents/puroks/Index', [
            'puroks' => PurokResource::collection($this->puroks->all())->resolve(),
        ]);
    }

    public function store(PurokRequest $request): RedirectResponse
    {
        $this->puroks->create($request->name(), $this->actor($request));
        Toast::success('messages.purok_created');

        return back();
    }

    public function update(PurokRequest $request, Purok $purok): RedirectResponse
    {
        $this->puroks->rename($purok, $request->name(), $this->actor($request));
        Toast::success('messages.purok_updated');

        return back();
    }

    public function deactivate(Request $request, Purok $purok): RedirectResponse
    {
        $this->puroks->setActive($purok, false, $this->actor($request));
        Toast::success('messages.purok_deactivated');

        return back();
    }

    public function reactivate(Request $request, Purok $purok): RedirectResponse
    {
        $this->puroks->setActive($purok, true, $this->actor($request));
        Toast::success('messages.purok_reactivated');

        return back();
    }

    public function destroy(Request $request, Purok $purok): RedirectResponse
    {
        $deleted = $this->puroks->remove($purok, $this->actor($request));
        Toast::success($deleted ? 'messages.purok_deleted' : 'messages.purok_deactivated_in_use');

        return back();
    }
}
