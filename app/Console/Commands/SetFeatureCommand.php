<?php

namespace App\Console\Commands;

use App\Enums\FeatureFlag;
use App\Enums\FeatureOverrideState;
use App\Models\Barangay;
use App\Services\Features\FeatureFlags;
use Illuminate\Console\Command;

class SetFeatureCommand extends Command
{
    protected $signature = 'bms:features:set
        {flag : Feature flag key}
        {state : on, off, or default}
        {--barangay= : PSGC code of the barangay; omit to change the global override}
        {--reason= : Why the change is made}';

    protected $description = 'Turn a feature flag on or off, or reset it to its default';

    public function handle(FeatureFlags $features): int
    {
        $flagArgument = $this->argument('flag');
        $stateArgument = $this->argument('state');
        $flag = is_string($flagArgument) ? FeatureFlag::tryFrom($flagArgument) : null;
        $state = is_string($stateArgument) ? FeatureOverrideState::tryFrom($stateArgument) : null;

        if ($flag === null || $state === null) {
            $this->components->error('Unknown flag or state. Run bms:features:list to see valid flags.');

            return self::INVALID;
        }

        $barangay = null;
        $code = $this->option('barangay');
        if (is_string($code) && $code !== '') {
            $barangay = Barangay::query()->where('psgc_code', $code)->first();
            if ($barangay === null) {
                $this->components->error("No barangay with PSGC code {$code}.");

                return self::FAILURE;
            }
        }

        $reason = $this->option('reason');
        $reason = is_string($reason) && $reason !== '' ? $reason : null;

        if ($state === FeatureOverrideState::Default) {
            $features->clearOverride($flag, $barangay, null);
        } else {
            $features->override($flag, $barangay, $state === FeatureOverrideState::On, $reason, null);
        }

        $resolved = $features->state($flag, $barangay);
        $this->components->info("{$flag->value} is now ".($resolved->enabled ? 'on' : 'off')." ({$resolved->source->value}).");

        return self::SUCCESS;
    }
}
