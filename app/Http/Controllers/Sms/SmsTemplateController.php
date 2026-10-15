<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sms\SmsTemplateRequest;
use App\Http\Resources\Sms\SmsTemplateResource;
use App\Http\Support\Toast;
use App\Models\SmsTemplate;
use App\Queries\SmsTemplateQuery;
use App\Services\Sms\SmsTemplateService;
use App\Support\Config\SmsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsTemplateController extends Controller
{
    public function __construct(private readonly SmsTemplateService $templates) {}

    public function index(SmsTemplateQuery $templates, SmsSettings $settings): Response
    {
        return Inertia::render('sms/templates/Index', [
            'templates' => array_values(SmsTemplateResource::collection($templates->all())->resolve()),
            'limits' => SmsComposeController::limits($settings),
        ]);
    }

    public function store(SmsTemplateRequest $request): RedirectResponse
    {
        $this->templates->save(null, $request->templateTitle(), $request->body(), $this->actor($request));
        Toast::success('messages.sms_template_saved');

        return back();
    }

    public function update(SmsTemplateRequest $request, SmsTemplate $template): RedirectResponse
    {
        $this->templates->save($template, $request->templateTitle(), $request->body(), $this->actor($request));
        Toast::success('messages.sms_template_saved');

        return back();
    }

    public function destroy(Request $request, SmsTemplate $template): RedirectResponse
    {
        $this->templates->delete($template, $this->actor($request));
        Toast::success('messages.sms_template_deleted');

        return back();
    }
}
