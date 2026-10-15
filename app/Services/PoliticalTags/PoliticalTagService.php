<?php

namespace App\Services\PoliticalTags;

use App\Enums\AuditAction;
use App\Models\Barangay;
use App\Models\ColorCategory;
use App\Models\Resident;
use App\Models\ResidentPoliticalTag;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use App\Tenancy\CurrentBarangay;
use Illuminate\Support\Facades\DB;

final readonly class PoliticalTagService
{
    public function __construct(
        private AuditTrail $audit,
        private CurrentBarangay $currentBarangay,
    ) {}

    /**
     * @param  list<int>  $residentIds
     */
    public function assign(array $residentIds, ?int $categoryId, User $actor): int
    {
        return DB::transaction(function () use ($residentIds, $categoryId, $actor): int {
            $category = $categoryId === null ? null : ColorCategory::query()->where('is_active', true)->findOrFail($categoryId);
            $residents = Resident::query()->whereKey($residentIds)->orderBy('id')->lockForUpdate()->get();
            $existing = ResidentPoliticalTag::query()->whereIn('resident_id', $residents->modelKeys())->get()->keyBy('resident_id');
            $changed = 0;

            foreach ($residents as $resident) {
                $tag = $existing->get($resident->id);
                $previous = $tag?->color_category_id;

                if ($previous === $category?->id) {
                    continue;
                }

                if ($category === null) {
                    $tag?->delete();
                } else {
                    ResidentPoliticalTag::query()->updateOrCreate(
                        ['resident_id' => $resident->id],
                        ['color_category_id' => $category->id, 'tagged_by' => $actor->id],
                    );
                }

                $this->audit->record(
                    AuditAction::PoliticalTagChanged,
                    $resident,
                    ['color_category_id' => $previous],
                    ['color_category_id' => $category?->id],
                    $resident->barangay_id,
                    $actor->id,
                );
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * @return array{tags: int, categories: int}
     */
    public function purge(Barangay $barangay, ?int $actorId): array
    {
        return $this->currentBarangay->runAs($barangay, fn (): array => DB::transaction(function () use ($barangay, $actorId): array {
            $tags = ResidentPoliticalTag::query()->delete();
            $categories = ColorCategory::query()->delete();

            $this->audit->record(
                AuditAction::PoliticalTagsPurged,
                after: ['tags' => $tags, 'categories' => $categories],
                barangayId: $barangay->id,
                actorId: $actorId,
            );

            return ['tags' => $tags, 'categories' => $categories];
        }));
    }
}
