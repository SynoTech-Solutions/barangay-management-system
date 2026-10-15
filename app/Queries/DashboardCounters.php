<?php

namespace App\Queries;

use App\Enums\BlotterStatus;
use App\Enums\CheckStatus;
use App\Enums\DashboardCounter;
use App\Enums\FeatureFlag;
use App\Enums\IssuedDocumentStatus;
use App\Enums\LiquidationStatus;
use App\Enums\PaymentType;
use App\Enums\Permission;
use App\Enums\SmsStatus;
use App\Enums\VoucherStatus;
use App\Models\BarangayClearance;
use App\Models\Blotter;
use App\Models\BusinessClearance;
use App\Models\Cedula;
use App\Models\Check;
use App\Models\FormTransaction;
use App\Models\IdCard;
use App\Models\IssuedDocument;
use App\Models\Liquidation;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Features\FeatureFlags;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class DashboardCounters
{
    private const string COUNT_EXPRESSION = 'count(*)';

    public function __construct(
        private FeatureFlags $features,
        private ExpiredBusinessQuery $expired,
    ) {}

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    public function for(User $user, CarbonImmutable $now): array
    {
        if (! $this->features->enabled(FeatureFlag::DashboardPendingCounters)) {
            return [];
        }

        $subqueries = $this->visible($user, $now);
        if ($subqueries === []) {
            return [];
        }

        $query = DB::query();
        foreach ($subqueries as $key => $subquery) {
            $query->selectSub($subquery->toBase()->reorder()->select(DB::raw(self::COUNT_EXPRESSION)), $key);
        }
        $row = (array) $query->first();

        $counters = [];
        foreach (array_keys($subqueries) as $key) {
            $counter = DashboardCounter::from($key);
            $counters[] = ['key' => $key, 'label' => (string) __($counter->labelKey()), 'count' => (int) ($row[$key] ?? 0)];
        }

        return $counters;
    }

    /**
     * @return array<string, Builder<covariant Model>>
     */
    private function visible(User $user, CarbonImmutable $now): array
    {
        $releaseStep = $this->features->enabled(FeatureFlag::DocumentReleaseStep);
        $clearances = $this->features->enabled(FeatureFlag::BarangayClearance) && $user->can(Permission::ClearancesView->value);
        $businessClearances = $this->features->enabled(FeatureFlag::BusinessClearance) && $user->can(Permission::ClearancesView->value);
        $idCards = $this->features->enabled(FeatureFlag::ResidentIdCards) && $user->can(Permission::ResidentsView->value);
        $cedulas = $this->features->enabled(FeatureFlag::Cedula) && $user->can(Permission::CedulaView->value);
        $blotters = $this->features->enabled(FeatureFlag::Blotter) && $user->can(Permission::BlotterView->value);
        $forms = $this->features->enabled(FeatureFlag::FormsEngine) && $user->can(Permission::FormTransactionsView->value);
        $finance = $this->features->enabled(FeatureFlag::Finance);
        $liquidation = $finance && $this->features->enabled(FeatureFlag::CashAdvanceLiquidation);
        $clearing = $liquidation && $this->features->enabled(FeatureFlag::LiquidationClearing);
        $sms = $this->features->enabled(FeatureFlag::Sms) && $user->can(Permission::SmsView->value);

        return array_filter([
            DashboardCounter::ClearancesForRelease->value => $clearances && $releaseStep ? $this->pending(BarangayClearance::query()) : null,
            DashboardCounter::ClearancesIssuedToday->value => $clearances ? $this->issuedOn(BarangayClearance::query(), $now) : null,
            DashboardCounter::BusinessClearancesForRelease->value => $businessClearances && $releaseStep ? $this->pending(BusinessClearance::query()) : null,
            DashboardCounter::BusinessClearancesIssuedToday->value => $businessClearances ? $this->issuedOn(BusinessClearance::query(), $now) : null,
            DashboardCounter::BusinessClearancesExpired->value => $businessClearances ? $this->expired->builder($now) : null,
            DashboardCounter::IdCardsForRelease->value => $idCards && $releaseStep ? $this->pending(IdCard::query()) : null,
            DashboardCounter::IdCardsIssuedToday->value => $idCards ? $this->issuedOn(IdCard::query(), $now) : null,
            DashboardCounter::CedulasForRelease->value => $cedulas && $releaseStep ? $this->pending(Cedula::query()) : null,
            DashboardCounter::CedulasIssuedToday->value => $cedulas ? $this->issuedOn(Cedula::query(), $now) : null,
            DashboardCounter::OpenBlotters->value => $blotters ? Blotter::query()->where('status', BlotterStatus::Open->value) : null,
            DashboardCounter::FormTransactionsForRelease->value => $forms && $releaseStep ? $this->pending(FormTransaction::query()) : null,
            DashboardCounter::VouchersForVerification->value => $finance && $user->can(Permission::VouchersVerify->value) ? Voucher::query()->where('status', VoucherStatus::Prepared->value) : null,
            DashboardCounter::VouchersForApproval->value => $finance && $user->can(Permission::VouchersApprove->value) ? Voucher::query()->where('status', VoucherStatus::Verified->value) : null,
            DashboardCounter::ChecksForRelease->value => $finance && $user->can(Permission::ChecksRelease->value) ? Check::query()->where('status', CheckStatus::ForRelease->value) : null,
            DashboardCounter::UnliquidatedCashAdvances->value => $liquidation && $user->can(Permission::LiquidationsManage->value) ? $this->unliquidated() : null,
            DashboardCounter::LiquidationsForClearing->value => $clearing && $user->can(Permission::LiquidationsClear->value) ? Liquidation::query()->where('status', LiquidationStatus::ForClearing->value) : null,
            DashboardCounter::SmsQueued->value => $sms ? SmsMessage::query()->whereIn('status', [SmsStatus::Queued->value, SmsStatus::Sending->value]) : null,
            DashboardCounter::SmsFailed->value => $sms ? SmsMessage::query()->where('status', SmsStatus::Failed->value) : null,
        ]);
    }

    /**
     * @return Builder<Voucher>
     */
    private function unliquidated(): Builder
    {
        return Voucher::query()
            ->where('status', VoucherStatus::Released->value)
            ->where('payment_type', PaymentType::CashAdvance->value)
            ->whereDoesntHave('liquidation');
    }

    /**
     * @template TDocument of IssuedDocument
     *
     * @param  Builder<TDocument>  $query
     * @return Builder<TDocument>
     */
    private function pending(Builder $query): Builder
    {
        return $query->where('status', IssuedDocumentStatus::Issued->value);
    }

    /**
     * @template TDocument of IssuedDocument
     *
     * @param  Builder<TDocument>  $query
     * @return Builder<TDocument>
     */
    private function issuedOn(Builder $query, CarbonImmutable $now): Builder
    {
        return $query->whereBetween('issued_at', [$now->startOfDay(), $now->endOfDay()]);
    }
}
