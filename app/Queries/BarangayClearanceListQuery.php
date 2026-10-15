<?php

namespace App\Queries;

use App\Models\BarangayClearance;
use App\Models\IssuedDocument;
use App\Models\Resident;
use App\Queries\Concerns\FiltersDocuments;
use App\Queries\Filters\DocumentListFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class BarangayClearanceListQuery
{
    use FiltersDocuments;

    private const array RELATIONS = ['resident:id,full_name,resident_number,deleted_at', 'clearanceType:id,label', 'purpose:id,label'];

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, BarangayClearance>
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
     * @return Collection<int, BarangayClearance>
     */
    public function forResident(Resident $resident): Collection
    {
        return BarangayClearance::query()
            ->with([...self::RELATIONS, ...IssuedDocument::LIFECYCLE_RELATIONS])
            ->where('resident_id', $resident->id)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Builder<BarangayClearance>
     */
    private function builder(DocumentListFilters $filters): Builder
    {
        return $this->filterDocuments(BarangayClearance::query(), $filters)
            ->with(self::RELATIONS)
            ->when($filters->clearanceTypeId !== null, fn (Builder $query) => $query->where('clearance_type_id', $filters->clearanceTypeId))
            ->when($filters->purposeId !== null, fn (Builder $query) => $query->where('purpose_id', $filters->purposeId))
            ->when(! $filters->name->isEmpty(), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('clearance_number ilike ?', [Like::startsWith($filters->name->raw)])
                ->orWhereRaw('control_number ilike ?', [Like::startsWith($filters->name->raw)])
                ->orWhereHas('resident', fn (Builder $resident) => $resident->matchingName($filters->name))));
    }
}
