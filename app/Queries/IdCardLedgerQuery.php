<?php

namespace App\Queries;

use App\Models\IdCard;
use App\Queries\Concerns\FiltersDocuments;
use App\Queries\Filters\DocumentListFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class IdCardLedgerQuery
{
    use FiltersDocuments;

    public const int PRINT_LIMIT = 1000;

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, IdCard>
     */
    public function paginate(DocumentListFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    /**
     * @return Collection<int, IdCard>
     */
    public function forPrint(DocumentListFilters $filters): Collection
    {
        return $this->builder($filters)->limit(self::PRINT_LIMIT)->get();
    }

    public function total(DocumentListFilters $filters): int
    {
        return $this->builder($filters)->count();
    }

    /**
     * @return Builder<IdCard>
     */
    private function builder(DocumentListFilters $filters): Builder
    {
        return $this->filterDocuments(IdCard::query(), $filters)
            ->with('resident:id,full_name,resident_number,deleted_at')
            ->when(! $filters->name->isEmpty(), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('card_number ilike ?', [Like::startsWith($filters->name->raw)])
                ->orWhereHas('resident', fn (Builder $resident) => $resident->matchingName($filters->name))));
    }
}
