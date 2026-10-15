<?php

namespace App\Services\PoliticalTags;

use App\Domain\Residents\NameNormalizer;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\ColorCategory;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ColorCategoryService
{
    public function __construct(private AuditTrail $audit) {}

    /**
     * @return Collection<int, ColorCategory>
     */
    public function all(): Collection
    {
        return ColorCategory::query()->withCount('tags')->orderBy('name')->get();
    }

    public function create(string $name, string $colorHex, User $actor): ColorCategory
    {
        $name = NameNormalizer::normalize($name) ?? '';
        $this->ensureNameAvailable($name, null);

        return DB::transaction(function () use ($name, $colorHex, $actor): ColorCategory {
            $category = ColorCategory::query()->create(['name' => $name, 'color_hex' => strtolower($colorHex)]);
            $this->audit->record(AuditAction::Created, $category, after: $category->only(['name', 'color_hex']), barangayId: $category->barangay_id, actorId: $actor->id);

            return $category;
        });
    }

    public function update(ColorCategory $category, string $name, string $colorHex, User $actor): ColorCategory
    {
        $name = NameNormalizer::normalize($name) ?? '';
        $this->ensureNameAvailable($name, $category->id);

        return DB::transaction(function () use ($category, $name, $colorHex, $actor): ColorCategory {
            $before = $category->only(['name', 'color_hex']);
            $category->fill(['name' => $name, 'color_hex' => strtolower($colorHex)])->save();
            $this->audit->record(AuditAction::Updated, $category, $before, $category->only(['name', 'color_hex']), $category->barangay_id, $actor->id);

            return $category;
        });
    }

    public function setActive(ColorCategory $category, bool $active, User $actor): void
    {
        DB::transaction(function () use ($category, $active, $actor): void {
            $category->is_active = $active;
            $category->save();
            $this->audit->record($active ? AuditAction::Reactivated : AuditAction::Deactivated, $category, barangayId: $category->barangay_id, actorId: $actor->id);
        });
    }

    private function ensureNameAvailable(string $name, ?int $exceptId): void
    {
        $taken = ColorCategory::query()
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw BmsException::of(ErrorCode::ColorCategoryNameTaken, ['name' => $name]);
        }
    }
}
