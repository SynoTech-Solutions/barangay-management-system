<?php

namespace App\Queries;

use App\Models\Business;
use App\Models\BusinessClearance;
use App\Models\IssuedDocument;
use App\Queries\Concerns\FiltersDocuments;
use App\Queries\Filters\DocumentListFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class BusinessClearanceListQuery
{
    use FiltersDocuments;

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, BusinessClearance>
     */
    public function paginate(DocumentListFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    /**
     * @return array{amount: string, doc_stamp: string, total: string}
     */
    public function totals(DocumentListFilters $filters): array
    {
        return $this->paymentTotals($this->builder($filters));
    }

    /**
     * @return Collection<int, BusinessClearance>
     */
    public function forBusiness(Business $business): Collection
    {
        return BusinessClearance::query()
            ->with(IssuedDocument::LIFECYCLE_RELATIONS)
            ->where('business_id', $business->id)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Builder<BusinessClearance>
     */
    private function builder(DocumentListFilters $filters): Builder
    {
        $term = $filters->name->raw;

        return $this->filterDocuments(BusinessClearance::query(), $filters)
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('clearance_number ilike ?', [Like::startsWith($term)])
                ->orWhereRaw('control_number ilike ?', [Like::startsWith($term)])
                ->orWhereRaw('business_name ilike ?', [Like::contains($term)])
                ->orWhereRaw('owner_name ilike ?', [Like::contains($term)])));
    }
}
