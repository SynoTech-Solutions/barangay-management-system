<?php

namespace App\Queries;

use App\Models\IndigencyCertificate;
use App\Queries\Concerns\FiltersDocuments;
use App\Queries\Filters\DocumentListFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class IndigencyCertificateListQuery
{
    use FiltersDocuments;

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, IndigencyCertificate>
     */
    public function paginate(DocumentListFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return $this->filterDocuments(IndigencyCertificate::query(), $filters)
            ->with(['resident:id,full_name,resident_number,deleted_at', 'purpose:id,label'])
            ->when($filters->purposeId !== null, fn (Builder $query) => $query->where('purpose_id', $filters->purposeId))
            ->when(! $filters->name->isEmpty(), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('certificate_number ilike ?', [Like::startsWith($filters->name->raw)])
                ->orWhereHas('resident', fn (Builder $resident) => $resident->matchingName($filters->name))))
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }
}
