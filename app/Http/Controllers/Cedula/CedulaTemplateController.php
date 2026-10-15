<?php

namespace App\Http\Controllers\Cedula;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cedula\CedulaTemplateRequest;
use App\Http\Resources\Cedula\CedulaTemplateResource;
use App\Http\Support\Toast;
use App\Models\CedulaTemplate;
use App\Queries\CedulaTemplateQuery;
use App\Services\Cedula\CedulaTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CedulaTemplateController extends Controller
{
    public function __construct(private readonly CedulaTemplateService $templates) {}

    public function index(CedulaTemplateQuery $templates): Response
    {
        return Inertia::render('cedulas/Templates', [
            'templates' => array_values(CedulaTemplateResource::collection($templates->all())->resolve()),
        ]);
    }

    public function store(CedulaTemplateRequest $request): RedirectResponse
    {
        $this->templates->save(null, $request->details(), $this->actor($request));
        Toast::success('messages.cedula_template_saved');

        return back();
    }

    public function update(CedulaTemplateRequest $request, CedulaTemplate $template): RedirectResponse
    {
        $this->templates->save($template, $request->details(), $this->actor($request));
        Toast::success('messages.cedula_template_saved');

        return back();
    }

    public function deactivate(Request $request, CedulaTemplate $template): RedirectResponse
    {
        $this->templates->setActive($template, false, $this->actor($request));
        Toast::success('messages.cedula_template_deactivated');

        return back();
    }

    public function reactivate(Request $request, CedulaTemplate $template): RedirectResponse
    {
        $this->templates->setActive($template, true, $this->actor($request));
        Toast::success('messages.cedula_template_reactivated');

        return back();
    }
}
