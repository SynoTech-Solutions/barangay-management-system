<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Services\Finance\ChartOfAccounts;
use Illuminate\Console\Command;

class InstallChartOfAccountsCommand extends Command
{
    protected $signature = 'bms:finance:install-chart
        {--barangay= : PSGC code of one barangay; every barangay when omitted}';

    protected $description = 'Copy the standard chart of accounts into barangays that have no accounts yet';

    public function handle(ChartOfAccounts $chart): int
    {
        $code = $this->option('barangay');
        $barangays = Barangay::query()
            ->when(is_string($code) && $code !== '', fn ($query) => $query->where('psgc_code', $code))
            ->orderBy('id')
            ->get();

        if ($barangays->isEmpty()) {
            $this->components->error('No matching barangay.');

            return self::INVALID;
        }

        foreach ($barangays as $barangay) {
            $count = $chart->installStandard($barangay);
            $this->components->info($count > 0 ? "Installed {$count} accounts in {$barangay->name}." : "{$barangay->name} already has accounts.");
        }

        return self::SUCCESS;
    }
}
