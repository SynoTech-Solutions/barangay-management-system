<?php

namespace App\Services\Residents;

use App\Domain\Residents\NameNormalizer;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class PurokService
{
    public function __construct(private AuditTrail $audit) {}

    /**
     * @return Collection<int, Purok>
     */
    public function all(): Collection
    {
        return Purok::query()->withCount('residents')->orderBy('name')->get();
    }

    public function create(string $name, User $actor): Purok
    {
        $name = $this->normalize($name);
        $this->ensureNameAvailable($name, null);

        return DB::transaction(function () use ($name, $actor): Purok {
            $purok = Purok::query()->create(['name' => $name]);

            $this->audit->record(AuditAction::PurokCreated, $purok, after: ['name' => $name], barangayId: $purok->barangay_id, actorId: $actor->id);

            return $purok;
        });
    }

    public function rename(Purok $purok, string $name, User $actor): Purok
    {
        $name = $this->normalize($name);
        $this->ensureNameAvailable($name, $purok->id);

        return DB::transaction(function () use ($purok, $name, $actor): Purok {
            $before = ['name' => $purok->name];
            $purok->name = $name;
            $purok->save();

            $this->audit->record(AuditAction::PurokUpdated, $purok, $before, ['name' => $name], $purok->barangay_id, $actor->id);

            return $purok;
        });
    }

    public function setActive(Purok $purok, bool $active, User $actor): void
    {
        DB::transaction(function () use ($purok, $active, $actor): void {
            $purok->is_active = $active;
            $purok->save();

            $this->audit->record(
                $active ? AuditAction::PurokReactivated : AuditAction::PurokDeactivated,
                $purok,
                barangayId: $purok->barangay_id,
                actorId: $actor->id,
            );
        });
    }

    public function remove(Purok $purok, User $actor): bool
    {
        if ($this->isReferenced($purok)) {
            $this->setActive($purok, false, $actor);

            return false;
        }

        DB::transaction(function () use ($purok, $actor): void {
            $purok->delete();

            $this->audit->record(AuditAction::Deleted, $purok, before: ['name' => $purok->name], barangayId: $purok->barangay_id, actorId: $actor->id);
        });

        return true;
    }

    private function isReferenced(Purok $purok): bool
    {
        return Resident::query()->withTrashed()->where('purok_id', $purok->id)->exists();
    }

    private function ensureNameAvailable(string $name, ?int $exceptId): void
    {
        $taken = Purok::query()
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw BmsException::of(ErrorCode::PurokNameTaken, ['name' => $name]);
        }
    }

    private function normalize(string $name): string
    {
        return NameNormalizer::normalize($name) ?? '';
    }
}
