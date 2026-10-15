<?php

namespace App\Queries;

use App\Domain\Residents\NameQuery;
use App\Http\Resources\Residents\ResidentOptionResource;
use App\Http\Support\Paginated;
use App\Models\Resident;
use App\Services\Holds\ResidentHoldChecker;
use App\Support\Config\BmsSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ResidentPickerQuery
{
    public const string HOLD_KEY = 'on_hold';

    public function __construct(
        private BmsSettings $settings,
        private ResidentHoldChecker $holds,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, Resident>  $results
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function payload(LengthAwarePaginator $results, bool $forIssuance): array
    {
        $page = Paginated::from($results, ResidentOptionResource::class);

        if (! $forIssuance) {
            return $page;
        }

        $held = $this->holds->heldAmong(array_values(array_map(fn (Resident $resident): int => $resident->id, $results->items())));
        $page['data'] = array_map(
            fn (array $option): array => [...$option, self::HOLD_KEY => in_array($option['id'] ?? null, $held, true)],
            $page['data'],
        );

        return $page;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function option(?int $residentId): ?array
    {
        $resident = $residentId === null ? null : Resident::query()->with('purok:id,name')->find($residentId);

        return $resident === null ? null : (new ResidentOptionResource($resident))->resolve();
    }

    /**
     * @return LengthAwarePaginator<int, Resident>
     */
    public function search(NameQuery $name, bool $includeDeceased, ?int $excludeId, ?int $perPage, bool $withMobile = false): LengthAwarePaginator
    {
        return Resident::query()
            ->with('purok:id,name')
            ->when($name->isEmpty(), fn ($query) => $query->whereRaw('false'))
            ->matchingName($name)
            ->when(! $includeDeceased, fn ($query) => $query->living())
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->when($withMobile, fn ($query) => $query->whereNotNull('mobile_number'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }
}
