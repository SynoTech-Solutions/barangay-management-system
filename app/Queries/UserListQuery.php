<?php

namespace App\Queries;

use App\Models\User;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class UserListQuery
{
    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(?int $barangayId, string $search, ?int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->with('roleAssignments.role')
            ->when($barangayId === null, fn ($query) => $query->whereNull('barangay_id'), fn ($query) => $query->where('barangay_id', $barangayId))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereRaw('name ilike ?', [Like::contains($search)])
                ->orWhereRaw('username ilike ?', [Like::contains($search)])))
            ->orderBy('name')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }
}
