<?php

namespace App\Console\Commands;

use App\Domain\Features\FlagCatalog;
use App\Domain\Features\FlagState;
use App\Models\Barangay;
use App\Services\Features\FeatureFlags;
use Illuminate\Console\Command;

class ListFeaturesCommand extends Command
{
    protected $signature = 'bms:features:list {--barangay= : PSGC code of the barangay; omit for the global view}';

    protected $description = 'Show every feature flag with its resolved state and source';

    public function handle(FeatureFlags $features): int
    {
        $barangay = $this->barangay();
        if ($barangay === false) {
            return self::FAILURE;
        }

        $this->table(
            ['Flag', 'Area', 'Kind', 'Default', 'Enabled', 'Source', 'Blocked by'],
            array_map(function (FlagState $state): array {
                $definition = FlagCatalog::definition($state->flag);

                return [
                    $state->flag->value,
                    $definition->area->value,
                    $definition->kind->value,
                    $definition->defaultEnabled ? 'on' : 'off',
                    $state->enabled ? 'on' : 'off',
                    $state->source->value,
                    $state->blockedBy === null ? '' : $state->blockedBy->value,
                ];
            }, array_values($features->all($barangay))),
        );

        return self::SUCCESS;
    }

    private function barangay(): Barangay|null|false
    {
        $code = $this->option('barangay');
        if (! is_string($code) || $code === '') {
            return null;
        }

        $barangay = Barangay::query()->where('psgc_code', $code)->first();
        if ($barangay === null) {
            $this->components->error("No barangay with PSGC code {$code}.");

            return false;
        }

        return $barangay;
    }
}
