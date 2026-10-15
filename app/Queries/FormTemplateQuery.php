<?php

namespace App\Queries;

use App\Domain\Forms\FormSnapshot;
use App\Domain\Forms\Placeholder;
use App\Domain\Forms\PlaceholderCatalog;
use App\Domain\Forms\SnapshotField;
use App\Enums\FeatureFlag;
use App\Enums\FormInputType;
use App\Enums\FormTemplateKind;
use App\Exceptions\BmsException;
use App\Http\Resources\Forms\FormTransactionResource;
use App\Models\FormPdfMapping;
use App\Models\FormPicklist;
use App\Models\FormPicklistItem;
use App\Models\FormTemplate;
use App\Models\FormTemplateField;
use App\Models\FormTemplateVersion;
use App\Models\FormTransaction;
use App\Models\IssuedDocument;
use App\Services\Features\FeatureFlags;
use App\Services\Forms\Pdf\FormPdfStore;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class FormTemplateQuery
{
    public function __construct(
        private BmsSettings $settings,
        private FeatureFlags $features,
        private FormPdfStore $pdfs,
    ) {}

    /**
     * @return list<array{namespace: string, label: string, tokens: list<array{token: string, label: string}>}>
     */
    public function tokenGroups(): array
    {
        $groups = [];
        foreach (PlaceholderCatalog::groups() as $namespace => $keys) {
            $groups[] = [
                'namespace' => $namespace,
                'label' => (string) __("forms.token_groups.{$namespace}"),
                'tokens' => array_map(fn (string $key): array => [
                    'token' => Placeholder::token($namespace, $key),
                    'label' => (string) __("forms.tokens.{$namespace}.{$key}"),
                ], $keys),
            ];
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function versions(FormTemplate $template): array
    {
        return array_values(FormTemplateVersion::query()
            ->with('creator:id,name')
            ->withCount('fields')
            ->where('form_template_id', $template->id)
            ->orderByDesc('version')
            ->get()
            ->map(fn (FormTemplateVersion $version): array => [
                'id' => $version->id,
                'version' => $version->version,
                'created_at' => $version->created_at->toIso8601String(),
                'created_by' => $version->creator?->name,
                'field_count' => (int) $version->getAttribute('fields_count'),
                'pdf_file_name' => $version->pdf_file_name,
                'is_current' => $version->id === $template->current_version_id,
            ])
            ->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function editor(FormTemplate $template): ?array
    {
        $version = $template->current_version_id === null ? null : FormTemplateVersion::query()->with(['fields', 'mappings'])->find($template->current_version_id);
        if ($version === null) {
            return null;
        }

        $pdfFields = [];
        if ($template->kind === FormTemplateKind::Pdf) {
            try {
                $pdfFields = array_map(fn ($field): array => $field->toArray(), $this->pdfs->existing($version)->fields ?? []);
            } catch (BmsException) {
                $pdfFields = [];
            }
        }

        return [
            'id' => $version->id,
            'version' => $version->version,
            'body' => $version->body,
            'pdf_file_name' => $version->pdf_file_name,
            'pdf_fields' => $pdfFields,
            'fields' => array_values($version->fields->map(fn (FormTemplateField $field): array => [
                'key' => $field->key,
                'label' => $field->label,
                'input_type' => $field->input_type->value,
                'is_required' => $field->is_required,
                'picklist_id' => $field->form_picklist_id,
            ])->all()),
            'mappings' => array_values($version->mappings->map(fn (FormPdfMapping $mapping): array => [
                'pdf_field' => $mapping->pdf_field,
                'pdf_field_type' => $mapping->pdf_field_type->value,
                'token' => $mapping->token,
                'checked_value' => $mapping->checked_value,
                'unchecked_value' => $mapping->unchecked_value,
            ])->all()),
        ];
    }

    /**
     * @return list<array{id: int, name: string, is_active: bool, items: list<array{id: int, label: string, is_active: bool}>}>
     */
    public function picklists(): array
    {
        return array_values(FormPicklist::query()->with('items')->orderBy('name')->get()->map(fn (FormPicklist $list): array => [
            'id' => $list->id,
            'name' => $list->name,
            'is_active' => $list->is_active,
            'items' => array_values($list->items->map(fn (FormPicklistItem $item): array => [
                'id' => $item->id,
                'label' => $item->label,
                'is_active' => $item->is_active,
            ])->all()),
        ])->all());
    }

    /**
     * @return LengthAwarePaginator<int, FormTemplate>
     */
    public function paginate(?int $typeId, bool $includeInactive, ?string $search, ?int $perPage): LengthAwarePaginator
    {
        return FormTemplate::query()
            ->with(['type:id,label', 'currentVersion:id,version,created_at'])
            ->when($typeId !== null, fn (Builder $query) => $query->where('type_id', $typeId))
            ->when(! $includeInactive, fn (Builder $query) => $query->where('is_active', true))
            ->when($search !== null && $search !== '', fn (Builder $query) => $query->whereRaw('name ilike ?', [Like::contains((string) $search)]))
            ->orderBy('name')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    /**
     * @return list<FormTemplateKind>
     */
    public function enabledKinds(): array
    {
        return array_values(array_filter(FormTemplateKind::cases(), fn (FormTemplateKind $kind): bool => $this->features->enabled($kind->flag())));
    }

    /**
     * @return list<FormInputType>
     */
    public function enabledInputTypes(): array
    {
        $picklists = $this->features->enabled(FeatureFlag::FormsTablePicklists);

        return array_values(array_filter(FormInputType::cases(), fn (FormInputType $type): bool => $type !== FormInputType::Table || $picklists));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function picklistOptions(): array
    {
        return array_values(FormPicklist::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (FormPicklist $list): array => ['value' => $list->id, 'label' => $list->name])->all());
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function templateOptions(): array
    {
        return array_values(FormTemplate::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (FormTemplate $template): array => ['value' => $template->id, 'label' => $template->name])->all());
    }

    /**
     * @return array{transaction: array<string, mixed>, values: object}
     */
    public function transactionEditor(FormTransaction $transaction): array
    {
        $transaction->load(['template:id,name,kind,type_id', 'template.type:id,label', 'version.fields', ...IssuedDocument::LIFECYCLE_RELATIONS]);
        $version = $transaction->version;
        if ($version === null) {
            throw (new ModelNotFoundException)->setModel(FormTemplateVersion::class);
        }

        $snapshot = FormSnapshot::fromArray($transaction->snapshot);
        $raw = $snapshot->rawValues();
        $picklistIds = array_values(array_filter($version->fields->pluck('form_picklist_id')->all(), 'is_int'));
        $keepIds = array_values(array_filter($raw, 'is_int'));

        return [
            'transaction' => [
                ...(new FormTransactionResource($transaction))->resolve(),
                'version' => $version->version,
                'fields' => $this->fields($version, $this->picklistItems($picklistIds, $keepIds)),
                'snapshot_fields' => array_map(fn (SnapshotField $field): array => $field->toArray(), $snapshot->fields),
            ],
            'values' => (object) $raw,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function issuable(): array
    {
        $templates = FormTemplate::query()
            ->with(['type:id,label', 'currentVersion.fields'])
            ->where('is_active', true)
            ->whereIn('kind', array_map(fn (FormTemplateKind $kind): string => $kind->value, $this->enabledKinds()))
            ->whereNotNull('current_version_id')
            ->orderBy('name')
            ->get();

        $picklistIds = $templates->flatMap(fn (FormTemplate $template) => $template->currentVersion?->fields->pluck('form_picklist_id') ?? [])->filter()->unique()->values()->all();
        $items = $this->picklistItems(array_values(array_map('intval', $picklistIds)));

        return array_values($templates->map(fn (FormTemplate $template): array => [
            'id' => $template->id,
            'name' => $template->name,
            'type' => $template->type?->label,
            'type_id' => $template->type_id,
            'kind' => $template->kind->value,
            'version' => $template->currentVersion?->version,
            'fields' => $template->currentVersion === null ? [] : $this->fields($template->currentVersion, $items),
        ])->all());
    }

    /**
     * @param  array<int, list<array{value: int, label: string}>>  $items
     * @return list<array<string, mixed>>
     */
    public function fields(FormTemplateVersion $version, array $items = []): array
    {
        $version->loadMissing('fields');

        return array_values($version->fields->map(fn (FormTemplateField $field): array => [
            'key' => $field->key,
            'label' => $field->label,
            'input_type' => $field->input_type->value,
            'is_required' => $field->is_required,
            'picklist_id' => $field->form_picklist_id,
            'options' => $field->form_picklist_id === null ? [] : ($items[$field->form_picklist_id] ?? []),
        ])->all());
    }

    /**
     * @param  list<int>  $picklistIds
     * @param  list<int>  $keepIds
     * @return array<int, list<array{value: int, label: string}>>
     */
    public function picklistItems(array $picklistIds, array $keepIds = []): array
    {
        if ($picklistIds === []) {
            return [];
        }

        $grouped = [];
        FormPicklistItem::query()
            ->whereIn('form_picklist_id', $picklistIds)
            ->where(fn (Builder $query) => $query->where('is_active', true)->when($keepIds !== [], fn (Builder $inner) => $inner->orWhereIn('id', $keepIds)))
            ->orderBy('label')
            ->get(['id', 'form_picklist_id', 'label'])
            ->each(function (FormPicklistItem $item) use (&$grouped): void {
                $grouped[$item->form_picklist_id][] = ['value' => $item->id, 'label' => $item->label];
            });

        return $grouped;
    }
}
