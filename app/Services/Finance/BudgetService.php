<?php

namespace App\Services\Finance;

use App\Domain\Finance\BudgetRevision;
use App\Domain\Money\Centavos;
use App\Enums\AccountKind;
use App\Enums\AuditAction;
use App\Enums\BudgetStatus;
use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class BudgetService
{
    public function __construct(
        private AuditTrail $audit,
        private FundGuard $funds,
    ) {}

    public function create(int $fiscalYear, string $title, User $actor): Budget
    {
        return DB::transaction(function () use ($fiscalYear, $title, $actor): Budget {
            if (Budget::query()->where('fiscal_year', $fiscalYear)->exists()) {
                throw BmsException::of(ErrorCode::BudgetYearTaken, ['year' => $fiscalYear]);
            }

            $budget = new Budget(['fiscal_year' => $fiscalYear, 'title' => $title]);
            $budget->status = BudgetStatus::Draft;
            $budget->created_by = $actor->id;
            $budget->save();

            $version = new BudgetVersion(['budget_id' => $budget->id, 'number' => BudgetVersion::ORIGINAL]);
            $version->created_by = $actor->id;
            $version->save();

            $this->audit->record(AuditAction::BudgetCreated, $budget, null, ['fiscal_year' => $fiscalYear, 'title' => $title], $budget->barangay_id, $actor->id);

            return $budget;
        });
    }

    public function rename(Budget $budget, string $title, User $actor): Budget
    {
        return DB::transaction(function () use ($budget, $title, $actor): Budget {
            $locked = $this->lockDraft($budget);
            $before = ['title' => $locked->title];
            $locked->title = $title;
            $locked->save();

            $this->audit->record(AuditAction::BudgetUpdated, $locked, $before, ['title' => $title], $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    /**
     * @param  array<int, string>  $amounts
     */
    public function prepare(Budget $budget, array $amounts, User $actor): Budget
    {
        return DB::transaction(function () use ($budget, $amounts, $actor): Budget {
            $locked = $this->lockDraft($budget);
            $next = array_filter(array_map(Centavos::fromDecimal(...), $amounts), fn (int $amount): bool => $amount !== 0);
            $this->ensurePosting(array_keys($next));

            $version = $locked->latestVersion();
            $previous = $version->amounts();
            $version->lines()->delete();
            $this->insertLines($version, $next, []);

            $this->audit->record(AuditAction::BudgetPrepared, $locked, ['amounts' => $this->byCode($previous)], ['amounts' => $this->byCode($next)], $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    public function copyFrom(Budget $target, int $sourceId, User $actor): Budget
    {
        return DB::transaction(function () use ($target, $sourceId, $actor): Budget {
            $locked = $this->lockDraft($target);
            $source = Budget::query()->find($sourceId);
            $sourceVersion = $source?->effectiveVersion();
            if ($source === null || $source->id === $locked->id || $sourceVersion === null) {
                throw BmsException::of(ErrorCode::BudgetSourceInvalid);
            }

            $version = $locked->latestVersion();
            if ($version->lines()->exists()) {
                throw BmsException::of(ErrorCode::BudgetNotEmpty, ['year' => $locked->fiscal_year]);
            }

            $postable = array_flip($this->postingAmong(array_keys($sourceVersion->amounts())));
            $copied = array_intersect_key($sourceVersion->amounts(), $postable);
            $this->insertLines($version, $copied, []);

            $this->audit->record(AuditAction::BudgetCopied, $locked, null, ['source_year' => $source->fiscal_year, 'amounts' => $this->byCode($copied)], $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    public function approve(Budget $budget, User $actor): Budget
    {
        return DB::transaction(function () use ($budget, $actor): Budget {
            $locked = $this->lockDraft($budget);
            $now = CarbonImmutable::now();

            $version = $locked->latestVersion();
            $version->approved_by = $actor->id;
            $version->approved_at = $now;
            $version->save();

            $locked->status = BudgetStatus::Approved;
            $locked->approved_by = $actor->id;
            $locked->approved_at = $now;
            $locked->save();

            $this->audit->record(AuditAction::BudgetApproved, $locked, ['status' => BudgetStatus::Draft->value], ['status' => BudgetStatus::Approved->value, 'version' => $version->number], $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    /**
     * @param  array<int, array{amount: string, remarks: string|null}>  $lines
     */
    public function revise(Budget $budget, array $lines, ?string $remarks, User $actor): BudgetVersion
    {
        return DB::transaction(function () use ($budget, $lines, $remarks, $actor): BudgetVersion {
            $locked = Budget::query()->lockForUpdate()->findOrFail($budget->id);
            $current = $locked->effectiveVersion();
            if (! $locked->isApproved() || $current === null) {
                throw BmsException::of(ErrorCode::BudgetNotApproved, ['year' => $locked->fiscal_year]);
            }

            $previous = $current->amounts();
            $next = $previous;
            $lineRemarks = [];
            foreach ($lines as $accountId => $line) {
                $next[$accountId] = Centavos::fromDecimal($line['amount']);
                $lineRemarks[$accountId] = $line['remarks'];
            }
            $next = array_filter($next, fn (int $amount): bool => $amount !== 0);

            $deltas = BudgetRevision::deltas($previous, $next);
            $this->ensureRevisable($previous, $next, $deltas, $lineRemarks);
            $this->ensureCommittedKept($locked, $next, $deltas);

            $version = new BudgetVersion(['budget_id' => $locked->id, 'number' => $current->number + 1, 'remarks' => $remarks]);
            $version->created_by = $actor->id;
            $version->save();
            $this->insertLines($version, $next, array_intersect_key($lineRemarks, $deltas));

            $version->approved_by = $actor->id;
            $version->approved_at = CarbonImmutable::now();
            $version->save();

            $this->audit->record(
                AuditAction::BudgetRevised,
                $locked,
                ['version' => $current->number],
                ['version' => $version->number, 'remarks' => $remarks, 'deltas' => $this->byCode($deltas)],
                $locked->barangay_id,
                $actor->id,
            );

            return $version;
        });
    }

    /**
     * @param  array<int, int>  $previous
     * @param  array<int, int>  $next
     * @param  array<int, int>  $deltas
     * @param  array<int, string|null>  $remarks
     */
    private function ensureRevisable(array $previous, array $next, array $deltas, array $remarks): void
    {
        if ($deltas === []) {
            throw BmsException::of(ErrorCode::BudgetRevisionUnchanged);
        }

        $imbalance = BudgetRevision::imbalance($previous, $next);
        if ($imbalance !== 0) {
            throw BmsException::of(ErrorCode::BudgetRevisionUnbalanced, ['difference' => Centavos::toDecimal($imbalance)]);
        }

        foreach (array_keys($deltas) as $accountId) {
            if (($remarks[$accountId] ?? '') === '') {
                throw BmsException::of(ErrorCode::BudgetRevisionNeedsRemarks, ['code' => $this->codes([$accountId])[$accountId] ?? (string) $accountId]);
            }
        }

        $this->ensurePosting(array_keys(array_filter($deltas, fn (int $delta): bool => $delta > 0)));
    }

    /**
     * @param  array<int, int>  $next
     * @param  array<int, int>  $deltas
     */
    private function ensureCommittedKept(Budget $budget, array $next, array $deltas): void
    {
        $committed = $this->funds->committed($budget);
        foreach ($deltas as $accountId => $delta) {
            $floor = $committed[$accountId] ?? 0;
            if ($delta < 0 && ($next[$accountId] ?? 0) < $floor) {
                throw BmsException::of(ErrorCode::BudgetRevisionBelowCommitted, [
                    'code' => $this->codes([$accountId])[$accountId] ?? (string) $accountId,
                    'amount' => Centavos::toDecimal($next[$accountId] ?? 0),
                    'committed' => Centavos::toDecimal($floor),
                ]);
            }
        }
    }

    private function lockDraft(Budget $budget): Budget
    {
        $locked = Budget::query()->lockForUpdate()->findOrFail($budget->id);
        if (! $locked->isDraft()) {
            throw BmsException::of(ErrorCode::BudgetNotDraft, ['year' => $locked->fiscal_year]);
        }

        return $locked;
    }

    /**
     * @param  array<int, int>  $amounts
     * @param  array<int, string|null>  $remarks
     */
    private function insertLines(BudgetVersion $version, array $amounts, array $remarks): void
    {
        foreach ($amounts as $accountId => $amount) {
            BudgetLine::query()->create([
                'budget_version_id' => $version->id,
                'account_id' => $accountId,
                'amount' => Centavos::toDecimal($amount),
                'remarks' => $remarks[$accountId] ?? null,
            ]);
        }
    }

    /**
     * @param  list<int>  $accountIds
     */
    private function ensurePosting(array $accountIds): void
    {
        $missing = array_diff($accountIds, $this->postingAmong($accountIds));
        if ($missing !== []) {
            $id = reset($missing);
            throw BmsException::of(ErrorCode::AccountNotPosting, ['code' => $this->codes([$id])[$id] ?? (string) $id]);
        }
    }

    /**
     * @param  list<int>  $accountIds
     * @return list<int>
     */
    private function postingAmong(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return array_values(Account::query()
            ->whereIn('id', $accountIds)
            ->where('is_active', true)
            ->where('kind', '<>', AccountKind::Group->value)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('accounts as children')->whereColumn('children.parent_id', 'accounts.id'))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all());
    }

    /**
     * @param  list<int>  $accountIds
     * @return array<int, string>
     */
    private function codes(array $accountIds): array
    {
        return Account::query()->whereIn('id', $accountIds)->pluck('code', 'id')->all();
    }

    /**
     * @param  array<int, int>  $amounts
     * @return array<string, string>
     */
    private function byCode(array $amounts): array
    {
        $codes = $this->codes(array_keys($amounts));
        $result = [];
        foreach ($amounts as $accountId => $amount) {
            $result[$codes[$accountId] ?? (string) $accountId] = Centavos::toDecimal($amount);
        }
        ksort($result);

        return $result;
    }
}
