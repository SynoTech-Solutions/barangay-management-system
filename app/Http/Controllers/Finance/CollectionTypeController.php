<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CollectionTypeRequest;
use App\Http\Resources\Finance\CollectionTypeResource;
use App\Http\Support\Toast;
use App\Models\CollectionType;
use App\Queries\CollectionTypeQuery;
use App\Services\Finance\CollectionTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionTypeController extends Controller
{
    public function __construct(private readonly CollectionTypeService $types) {}

    public function index(CollectionTypeQuery $query): Response
    {
        return Inertia::render('collections/Types', [
            'types' => array_values(CollectionTypeResource::collection($query->all())->resolve()),
        ]);
    }

    public function store(CollectionTypeRequest $request): RedirectResponse
    {
        $this->types->save(null, $request->name(), $this->actor($request));
        Toast::success('messages.collection_type_saved');

        return back();
    }

    public function update(CollectionTypeRequest $request, CollectionType $collectionType): RedirectResponse
    {
        $this->types->save($collectionType, $request->name(), $this->actor($request));
        Toast::success('messages.collection_type_saved');

        return back();
    }

    public function deactivate(Request $request, CollectionType $collectionType): RedirectResponse
    {
        $this->types->setActive($collectionType, false, $this->actor($request));
        Toast::success('messages.record_deactivated');

        return back();
    }

    public function reactivate(Request $request, CollectionType $collectionType): RedirectResponse
    {
        $this->types->setActive($collectionType, true, $this->actor($request));
        Toast::success('messages.record_reactivated');

        return back();
    }
}
