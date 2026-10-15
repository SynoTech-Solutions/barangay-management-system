<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\FormPicklistItemRequest;
use App\Http\Requests\Forms\FormPicklistRequest;
use App\Http\Support\Toast;
use App\Models\FormPicklist;
use App\Models\FormPicklistItem;
use App\Queries\FormTemplateQuery;
use App\Services\Forms\FormPicklistService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FormPicklistController extends Controller
{
    public function __construct(private readonly FormPicklistService $picklists) {}

    public function index(FormTemplateQuery $query): Response
    {
        return Inertia::render('forms/picklists/Index', ['picklists' => $query->picklists()]);
    }

    public function store(FormPicklistRequest $request): RedirectResponse
    {
        $this->picklists->create($request->name(), $this->actor($request));
        Toast::success('messages.form_picklist_saved');

        return back();
    }

    public function update(FormPicklistRequest $request, FormPicklist $picklist): RedirectResponse
    {
        $this->picklists->update($picklist, $request->name(), $request->active(), $this->actor($request));
        Toast::success('messages.form_picklist_saved');

        return back();
    }

    public function storeItem(FormPicklistItemRequest $request, FormPicklist $picklist): RedirectResponse
    {
        $this->picklists->addItem($picklist, $request->label(), $this->actor($request));
        Toast::success('messages.form_picklist_saved');

        return back();
    }

    public function updateItem(FormPicklistItemRequest $request, FormPicklist $picklist, FormPicklistItem $item): RedirectResponse
    {
        abort_unless($item->form_picklist_id === $picklist->id, SymfonyResponse::HTTP_NOT_FOUND);
        $this->picklists->updateItem($item, $request->label(), $request->active(), $this->actor($request));
        Toast::success('messages.form_picklist_saved');

        return back();
    }
}
