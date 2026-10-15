<?php

namespace App\Services\Lookups;

use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\LookupType;
use App\Exceptions\BmsException;
use App\Models\Barangay;
use App\Models\LookupValue;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class LookupService
{
    public function __construct(private AuditTrail $audit) {}

    /**
     * @return Collection<int, LookupValue>
     */
    public function all(LookupType $type, ?Barangay $barangay): Collection
    {
        return $this->visible($type, $barangay)->orderBy('sort_order')->orderBy('label')->get();
    }

    /**
     * @return Collection<int, LookupValue>
     */
    public function options(LookupType $type, ?Barangay $barangay): Collection
    {
        return $this->visible($type, $barangay)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'label']);
    }

    public function create(LookupType $type, string $label, ?Barangay $barangay, int $actorId): LookupValue
    {
        $label = $this->normalize($label);
        $this->ensureLabelAvailable($type, $label, $barangay, null);

        return DB::transaction(function () use ($type, $label, $barangay, $actorId): LookupValue {
            $value = LookupValue::query()->create([
                'barangay_id' => $barangay?->id,
                'type' => $type,
                'label' => $label,
            ]);

            $this->audit->record(AuditAction::Created, $value, after: $value->only(['type', 'label']), barangayId: $barangay?->id, actorId: $actorId);

            return $value;
        });
    }

    public function rename(LookupValue $value, string $label, ?Barangay $barangay, int $actorId): LookupValue
    {
        $this->ensureEditable($value, $barangay);
        $label = $this->normalize($label);
        $this->ensureLabelAvailable($value->type, $label, $barangay, $value->id);

        return DB::transaction(function () use ($value, $label, $barangay, $actorId): LookupValue {
            $before = $value->only(['label']);
            $value->label = $label;
            $value->save();

            $this->audit->record(AuditAction::Updated, $value, $before, $value->only(['label']), $barangay?->id, $actorId);

            return $value;
        });
    }

    public function setActive(LookupValue $value, bool $active, ?Barangay $barangay, int $actorId): void
    {
        $this->ensureEditable($value, $barangay);

        DB::transaction(function () use ($value, $active, $barangay, $actorId): void {
            $value->is_active = $active;
            $value->save();

            $this->audit->record($active ? AuditAction::Reactivated : AuditAction::Deactivated, $value, barangayId: $barangay?->id, actorId: $actorId);
        });
    }

    /**
     * @return Builder<LookupValue>
     */
    private function visible(LookupType $type, ?Barangay $barangay): Builder
    {
        return LookupValue::query()
            ->where('type', $type)
            ->where(fn (Builder $query) => $query->whereNull('barangay_id')->when(
                $barangay !== null,
                fn (Builder $inner) => $inner->orWhere('barangay_id', $barangay?->id),
            ));
    }

    private function ensureEditable(LookupValue $value, ?Barangay $barangay): void
    {
        if ($value->barangay_id !== $barangay?->id) {
            throw BmsException::of(ErrorCode::GlobalLookupReadOnly);
        }
    }

    private function ensureLabelAvailable(LookupType $type, string $label, ?Barangay $barangay, ?int $exceptId): void
    {
        $taken = $this->visible($type, $barangay)
            ->whereRaw('lower(label) = lower(?)', [$label])
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw BmsException::of(ErrorCode::LookupLabelTaken, ['label' => $label]);
        }
    }

    private function normalize(string $label): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $label));
    }
}
