<?php

namespace App\Http\Controllers\Forms;

use App\Enums\FormInputType;
use App\Enums\FormTemplateKind;
use App\Enums\LookupType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\FormTemplateListRequest;
use App\Http\Requests\Forms\FormTemplateRequest;
use App\Http\Resources\Forms\FormTemplateResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\FormTemplate;
use App\Queries\FormOptionsQuery;
use App\Queries\FormTemplateQuery;
use App\Services\Forms\FormTemplateService;
use App\Support\RouteNames;
use App\Tenancy\CurrentBarangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormTemplateController extends Controller
{
    public function __construct(
        private readonly FormTemplateService $templates,
        private readonly FormTemplateQuery $query,
    ) {}

    public function index(FormTemplateListRequest $request, FormOptionsQuery $options): Response
    {
        return Inertia::render('forms/templates/Index', [
            'templates' => Paginated::from($this->query->paginate($request->typeId(), $request->includeInactive(), $request->search(), $request->perPage()), FormTemplateResource::class),
            'filters' => $request->filters(),
            'types' => $options->lookups([LookupType::FormTemplateType])[LookupType::FormTemplateType->value],
        ]);
    }

    public function create(FormOptionsQuery $options): Response
    {
        return Inertia::render('forms/templates/Form', [
            'template' => null,
            'editor' => null,
            ...$this->formOptions($options, null),
        ]);
    }

    public function store(FormTemplateRequest $request, CurrentBarangay $currentBarangay): RedirectResponse
    {
        $template = $this->templates->create(
            $request->typeId(),
            $request->name(),
            $request->kind(),
            $request->content(),
            $request->pdf(),
            $currentBarangay->require()->id,
            $this->actor($request),
        );
        Toast::success('messages.form_template_created', ['number' => $template->template_number]);

        return to_route(RouteNames::FORM_TEMPLATES_SHOW, $template);
    }

    public function show(FormTemplate $template): Response
    {
        $template->load(['type:id,label', 'currentVersion:id,version,created_at']);

        return Inertia::render('forms/templates/Show', [
            'template' => (new FormTemplateResource($template))->resolve(),
            'versions' => $this->query->versions($template),
            'currentVersion' => $this->query->editor($template),
        ]);
    }

    public function edit(FormTemplate $template, FormOptionsQuery $options): Response
    {
        $this->templates->ensureKindEnabled($template->kind);
        $template->load(['type:id,label', 'currentVersion:id,version,created_at']);

        return Inertia::render('forms/templates/Form', [
            'template' => (new FormTemplateResource($template))->resolve(),
            'editor' => $this->query->editor($template),
            ...$this->formOptions($options, $template),
        ]);
    }

    public function update(FormTemplateRequest $request, FormTemplate $template): RedirectResponse
    {
        $template->load('currentVersion');
        $version = $this->templates->update($template, $request->typeId(), $request->name(), $request->content(), $request->pdf(), $this->actor($request));

        $version === null
            ? Toast::success('messages.form_template_unchanged')
            : Toast::success('messages.form_template_saved', ['version' => $version->version]);

        return to_route(RouteNames::FORM_TEMPLATES_SHOW, $template);
    }

    public function deactivate(Request $request, FormTemplate $template): RedirectResponse
    {
        $this->templates->setActive($template, false, $this->actor($request));
        Toast::success('messages.form_template_deactivated');

        return back();
    }

    public function reactivate(Request $request, FormTemplate $template): RedirectResponse
    {
        $this->templates->setActive($template, true, $this->actor($request));
        Toast::success('messages.form_template_reactivated');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(FormOptionsQuery $options, ?FormTemplate $template): array
    {
        return [
            'types' => $options->lookups([LookupType::FormTemplateType], $template === null ? [] : [$template->type_id])[LookupType::FormTemplateType->value],
            'kinds' => EnumOptions::of($this->query->enabledKinds()),
            'inputTypes' => EnumOptions::of($this->query->enabledInputTypes()),
            'picklists' => $this->query->picklistOptions(),
            'tokens' => $this->query->tokenGroups(),
            'limits' => [
                'pdf_max_kilobytes' => FormTemplateRequest::PDF_MAX_KILOBYTES,
                'fields_max' => FormTemplateRequest::FIELDS_MAX,
            ],
            'allKinds' => EnumOptions::of(FormTemplateKind::cases()),
            'allInputTypes' => EnumOptions::of(FormInputType::cases()),
        ];
    }
}
