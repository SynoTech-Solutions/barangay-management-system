<?php

namespace App\Services\Features;

use App\Domain\Features\FlagInputs;
use App\Domain\Features\FlagResolver;
use App\Domain\Features\FlagState;
use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\LogEvent;
use App\Models\Barangay;
use App\Models\FeatureOverride;
use App\Services\Audit\AuditTrail;
use App\Support\CacheKeys;
use App\Support\Config\BmsSettings;
use App\Tenancy\CurrentBarangay;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class FeatureFlags
{
    /**
     * @var array<string, array<string, FlagState>>
     */
    private array $memo = [];

    public function __construct(
        private readonly BmsSettings $settings,
        private readonly Cache $cache,
        private readonly CurrentBarangay $currentBarangay,
        private readonly AuditTrail $audit,
    ) {}

    public function enabled(FeatureFlag $flag, ?Barangay $barangay = null): bool
    {
        return $this->state($flag, $barangay)->enabled;
    }

    public function state(FeatureFlag $flag, ?Barangay $barangay = null): FlagState
    {
        return $this->all($barangay)[$flag->value];
    }

    /**
     * @return array<string, FlagState>
     */
    public function all(?Barangay $barangay = null): array
    {
        $barangayId = ($barangay ?? $this->currentBarangay->get())?->id;
        $memoKey = 'b'.($barangayId ?? '-');

        return $this->memo[$memoKey] ??= FlagResolver::resolveAll(new FlagInputs(
            forcedOff: $this->settings->features->forcedOff,
            globalOverrides: $this->overrides(null),
            barangayOverrides: $barangayId === null ? [] : $this->overrides($barangayId),
        ));
    }

    /**
     * @return array<string, bool>
     */
    public function enabledMap(?Barangay $barangay = null): array
    {
        return array_map(fn (FlagState $state): bool => $state->enabled, $this->all($barangay));
    }

    public function override(FeatureFlag $flag, ?Barangay $barangay, bool $enabled, ?string $reason, ?int $actorId): void
    {
        DB::transaction(function () use ($flag, $barangay, $enabled, $reason, $actorId): void {
            $existing = $this->findOverride($flag, $barangay);
            $before = $existing === null ? null : ['enabled' => $existing->enabled];

            FeatureOverride::query()->updateOrCreate(
                ['barangay_id' => $barangay?->id, 'flag' => $flag->value],
                ['enabled' => $enabled, 'reason' => $reason, 'updated_by' => $actorId],
            );

            $this->audit->record(
                AuditAction::FeatureOverridden,
                before: $before === null ? null : ['flag' => $flag->value, ...$before],
                after: ['flag' => $flag->value, 'enabled' => $enabled, 'reason' => $reason],
                barangayId: $barangay?->id,
                actorId: $actorId,
            );
        });

        $this->forget($barangay);

        Log::info(LogEvent::FeatureOverrideSet->value, [
            'flag' => $flag->value,
            'barangay_id' => $barangay?->id,
            'enabled' => $enabled,
            'reason' => $reason,
        ]);
    }

    public function clearOverride(FeatureFlag $flag, ?Barangay $barangay, ?int $actorId): void
    {
        DB::transaction(function () use ($flag, $barangay, $actorId): void {
            $existing = $this->findOverride($flag, $barangay);
            if ($existing === null) {
                return;
            }

            $existing->delete();

            $this->audit->record(
                AuditAction::FeatureOverrideCleared,
                before: ['flag' => $flag->value, 'enabled' => $existing->enabled],
                barangayId: $barangay?->id,
                actorId: $actorId,
            );
        });

        $this->forget($barangay);

        Log::info(LogEvent::FeatureOverrideCleared->value, [
            'flag' => $flag->value,
            'barangay_id' => $barangay?->id,
        ]);
    }

    private function findOverride(FeatureFlag $flag, ?Barangay $barangay): ?FeatureOverride
    {
        return FeatureOverride::query()
            ->where('flag', $flag->value)
            ->when(
                $barangay === null,
                fn ($query) => $query->whereNull('barangay_id'),
                fn ($query) => $query->where('barangay_id', $barangay?->id),
            )
            ->first();
    }

    private function forget(?Barangay $barangay): void
    {
        $this->cache->forget(CacheKeys::featureOverrides($barangay?->id));
        $this->memo = [];
    }

    /**
     * @return array<string, bool>
     */
    private function overrides(?int $barangayId): array
    {
        $load = fn (): array => FeatureOverride::query()
            ->when(
                $barangayId === null,
                fn ($query) => $query->whereNull('barangay_id'),
                fn ($query) => $query->where('barangay_id', $barangayId),
            )
            ->pluck('enabled', 'flag')
            ->map(fn ($value): bool => (bool) $value)
            ->all();

        $ttl = $this->settings->features->overrideCacheSeconds;

        if ($ttl === 0) {
            return $load();
        }

        return $this->cache->remember(CacheKeys::featureOverrides($barangayId), $ttl, $load);
    }
}
