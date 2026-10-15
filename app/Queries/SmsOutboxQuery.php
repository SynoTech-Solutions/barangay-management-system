<?php

namespace App\Queries;

use App\Domain\Residents\PhilippineMobileNumber;
use App\Models\SmsMessage;
use App\Queries\Filters\SmsListFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class SmsOutboxQuery
{
    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, SmsMessage>
     */
    public function paginate(SmsListFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        $search = $filters->search;
        $number = PhilippineMobileNumber::toE164($search);

        return SmsMessage::query()
            ->with(['resident:id,full_name,deleted_at', 'queuedBy:id,name'])
            ->when($filters->status !== null, fn (Builder $query) => $query->where('status', $filters->status?->value))
            ->when($filters->from !== null, fn (Builder $query) => $query->where('queued_at', '>=', $filters->from))
            ->when($filters->to !== null, fn (Builder $query) => $query->where('queued_at', '<=', $filters->to))
            ->when($search !== null && $search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search, $number): void {
                $inner->whereRaw('body ilike ?', [Like::contains((string) $search)])
                    ->orWhereHas('resident', fn (Builder $resident) => $resident->whereRaw('full_name ilike ?', [Like::contains((string) $search)]))
                    ->when($number !== null, fn (Builder $q) => $q->orWhere('recipient', $number));
            }))
            ->orderByDesc('queued_at')
            ->orderByDesc('id')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }
}
