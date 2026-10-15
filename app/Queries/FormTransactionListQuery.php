<?php

namespace App\Queries;

use App\Domain\Forms\FormSnapshot;
use App\Models\FormTemplate;
use App\Models\FormTemplateField;
use App\Models\FormTemplateVersion;
use App\Models\FormTransaction;
use App\Models\IssuedDocument;
use App\Models\Resident;
use App\Queries\Filters\FormTransactionFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

final readonly class FormTransactionListQuery
{
    public const int PRINT_LIMIT = 1000;

    private const int CHUNK_SIZE = 500;

    private const string ZERO = '0.00';

    private const array RELATIONS = ['resident:id,full_name,resident_number,deleted_at', 'template:id,name,type_id,kind', 'template.type:id,label'];

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, FormTransaction>
     */
    public function paginate(FormTransactionFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    /**
     * @return Collection<int, FormTransaction>
     */
    public function forPrint(FormTransactionFilters $filters): Collection
    {
        return $this->builder($filters)->limit(self::PRINT_LIMIT)->get();
    }

    /**
     * @return LazyCollection<int, FormTransaction>
     */
    public function lazy(FormTransactionFilters $filters): LazyCollection
    {
        return $this->builder($filters)->lazy(self::CHUNK_SIZE);
    }

    public function count(FormTransactionFilters $filters): int
    {
        return $this->builder($filters)->reorder()->count();
    }

    public function total(FormTransactionFilters $filters): string
    {
        $total = $this->builder($filters)->notCancelled()->reorder()->toBase()
            ->selectRaw('coalesce(sum(amount), 0)::numeric(14, 2)::text as total')
            ->value('total');

        return is_string($total) ? $total : self::ZERO;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function fieldColumns(FormTransactionFilters $filters): array
    {
        $template = $filters->templateId === null ? null : FormTemplate::query()->find($filters->templateId);
        $version = $template?->current_version_id === null ? null : FormTemplateVersion::query()->with('fields')->find($template->current_version_id);

        return $version === null ? [] : array_values($version->fields->map(fn (FormTemplateField $field): array => ['key' => $field->key, 'label' => $field->label])->all());
    }

    /**
     * @param  list<array{key: string, label: string}>  $columns
     * @return array<string, string>
     */
    public function fieldValues(FormTransaction $transaction, array $columns): array
    {
        $snapshot = FormSnapshot::fromArray($transaction->snapshot);
        $values = [];
        foreach ($columns as $column) {
            $values[$column['key']] = $snapshot->field($column['key'])->display ?? '';
        }

        return $values;
    }

    /**
     * @return Collection<int, FormTransaction>
     */
    public function forResident(Resident $resident): Collection
    {
        return FormTransaction::query()
            ->with([...self::RELATIONS, ...IssuedDocument::LIFECYCLE_RELATIONS])
            ->where('resident_id', $resident->id)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Builder<FormTransaction>
     */
    private function builder(FormTransactionFilters $filters): Builder
    {
        return FormTransaction::query()
            ->with([...self::RELATIONS, ...IssuedDocument::LIFECYCLE_RELATIONS])
            ->when($filters->status !== null, fn (Builder $query) => $query->where('status', $filters->status))
            ->when($filters->from !== null, fn (Builder $query) => $query->where('issued_at', '>=', $filters->from))
            ->when($filters->to !== null, fn (Builder $query) => $query->where('issued_at', '<=', $filters->to))
            ->when($filters->templateId !== null, fn (Builder $query) => $query->where('form_template_id', $filters->templateId))
            ->when($filters->typeId !== null, fn (Builder $query) => $query->whereHas('template', fn (Builder $template) => $template->where('type_id', $filters->typeId)))
            ->when(! $filters->name->isEmpty(), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('transaction_number ilike ?', [Like::startsWith($filters->name->raw)])
                ->orWhereHas('resident', fn (Builder $resident) => $resident->matchingName($filters->name))))
            ->orderByDesc('issued_at')
            ->orderByDesc('id');
    }
}
