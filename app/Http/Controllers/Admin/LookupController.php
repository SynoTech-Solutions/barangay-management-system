<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LookupType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LookupValueRequest;
use App\Http\Resources\LookupValueResource;
use App\Http\Support\Toast;
use App\Models\LookupValue;
use App\Services\Lookups\LookupService;
use App\Tenancy\CurrentBarangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class LookupController extends Controller
{
    public function __construct(
        private readonly LookupService $lookups,
        private readonly CurrentBarangay $currentBarangay,
    ) {}

    public function index(LookupType $type): Response
    {
        return Inertia::render('admin/lookups/Index', [
            'type' => $type->value,
            'types' => array_map(fn (LookupType $case): array => ['value' => $case->value, 'label' => __($case->labelKey())], LookupType::cases()),
            'values' => LookupValueResource::collection($this->lookups->all($type, $this->currentBarangay->get()))->resolve(),
        ]);
    }

    public function store(LookupValueRequest $request, LookupType $type): RedirectResponse
    {
        $this->lookups->create($type, $request->label(), $this->currentBarangay->get(), $this->actor($request)->id);
        Toast::success('messages.lookup_created');

        return back();
    }

    public function update(LookupValueRequest $request, LookupType $type, LookupValue $value): RedirectResponse
    {
        $this->ensureType($value, $type);
        $this->lookups->rename($value, $request->label(), $this->currentBarangay->get(), $this->actor($request)->id);
        Toast::success('messages.lookup_updated');

        return back();
    }

    public function deactivate(Request $request, LookupType $type, LookupValue $value): RedirectResponse
    {
        $this->ensureType($value, $type);
        $this->lookups->setActive($value, false, $this->currentBarangay->get(), $this->actor($request)->id);
        Toast::success('messages.lookup_deactivated');

        return back();
    }

    public function reactivate(Request $request, LookupType $type, LookupValue $value): RedirectResponse
    {
        $this->ensureType($value, $type);
        $this->lookups->setActive($value, true, $this->currentBarangay->get(), $this->actor($request)->id);
        Toast::success('messages.lookup_reactivated');

        return back();
    }

    private function ensureType(LookupValue $value, LookupType $type): void
    {
        abort_unless($value->type === $type, HttpStatus::HTTP_NOT_FOUND);
    }
}
