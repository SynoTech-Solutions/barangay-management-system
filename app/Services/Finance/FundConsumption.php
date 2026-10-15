<?php

namespace App\Services\Finance;

use App\Domain\Finance\ConsumptionEntry;
use App\Domain\Finance\DisbursementRecord;
use App\Models\Account;
use App\Models\Budget;

interface FundConsumption
{
    /**
     * @return array<int, list<ConsumptionEntry>>
     */
    public function entriesByAccount(Budget $budget): array;

    /**
     * @return list<DisbursementRecord>
     */
    public function disbursements(Budget $budget, Account $account): array;
}
