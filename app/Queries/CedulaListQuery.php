<?php

namespace App\Queries;

use App\Models\Cedula;
use App\Queries\Concerns\FiltersDocuments;
use App\Queries\Filters\DocumentListFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class CedulaListQuery
{
    use FiltersDocuments;

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, Cedula>
     */
    public function paginate(DocumentListFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    public function total(DocumentListFilters $filters): string
    {
        return $this->amountTotal($this->builder($filters));
    }

    /**
     * @return Builder<Cedula>
     */
    private function builder(DocumentListFilters $filters): Builder
    {
        return $this->filterDocuments(Cedula::query(), $filters)
            ->with('resident:id,full_name,resident_number,deleted_at')
            ->when(! $filters->name->isEmpty(), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('cedula_number ilike ?', [Like::startsWith($filters->name->raw)])
                ->orWhereRaw('form_number ilike ?', [Like::startsWith($filters->name->raw)])
                ->orWhereHas('resident', fn (Builder $resident) => $resident->matchingName($filters->name))));
    }
}
