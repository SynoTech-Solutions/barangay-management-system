<?php

namespace App\Queries;

use App\Models\Blotter;
use App\Models\BlotterParty;
use App\Models\Resident;
use App\Queries\Filters\BlotterListFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class BlotterListQuery
{
    private const array LIST_RELATIONS = ['incidentType:id,label', 'parties:id,blotter_id,role'];

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, Blotter>
     */
    public function paginate(BlotterListFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return Blotter::query()
            ->with(self::LIST_RELATIONS)
            ->when($filters->status !== null, fn (Builder $query) => $query->where('status', $filters->status))
            ->when($filters->incidentTypeId !== null, fn (Builder $query) => $query->where('incident_type_id', $filters->incidentTypeId))
            ->when($filters->from !== null, fn (Builder $query) => $query->where('incident_at', '>=', $filters->from))
            ->when($filters->to !== null, fn (Builder $query) => $query->where('incident_at', '<=', $filters->to))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('blotter_number ilike ?', [Like::startsWith($filters->search)])
                ->orWhereHas('parties', fn (Builder $party) => $party
                    ->whereRaw('name ilike ?', [Like::contains($filters->search)])
                    ->orWhereHas('resident', fn (Builder $resident) => $resident->whereRaw('full_name ilike ?', [Like::contains($filters->search)])))))
            ->orderByDesc('incident_at')
            ->orderByDesc('id')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    public function details(Blotter $blotter, bool $withPhotos): Blotter
    {
        return $blotter->load([
            'incidentType:id,label',
            'parties.resident:id,full_name,resident_number,birthdate,is_deceased,deleted_at',
            'recorder:id,name',
            'resolver:id,name',
            'canceller:id,name',
            ...($withPhotos ? ['photos'] : []),
        ]);
    }

    /**
     * @return list<array{id: int, blotter_number: string, incident_at: string, incident_type: string|null, status: string, status_label: string, role: string, role_label: string}>
     */
    public function forResident(Resident $resident): array
    {
        /** @var Collection<int, BlotterParty> $parties */
        $parties = BlotterParty::query()
            ->with(['blotter.incidentType:id,label'])
            ->where('resident_id', $resident->id)
            ->orderByDesc('id')
            ->get();

        return array_values($parties
            ->filter(fn (BlotterParty $party): bool => $party->blotter !== null)
            ->map(fn (BlotterParty $party): array => [
                'id' => $party->blotter->id,
                'blotter_number' => $party->blotter->blotter_number,
                'incident_at' => $party->blotter->incident_at->toIso8601String(),
                'incident_type' => $party->blotter->incidentType?->label,
                'status' => $party->blotter->status->value,
                'status_label' => (string) __($party->blotter->status->labelKey()),
                'role' => $party->role->value,
                'role_label' => (string) __($party->role->labelKey()),
            ])
            ->all());
    }
}
