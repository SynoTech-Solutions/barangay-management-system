<?php

namespace App\Queries;

use App\Domain\Finance\AmountInWords;
use App\Domain\Money\Centavos;
use App\Enums\FeatureFlag;
use App\Http\Resources\Finance\LiquidationResource;
use App\Http\Resources\Finance\VoucherResource;
use App\Models\Check;
use App\Models\Liquidation;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Services\Features\FeatureFlags;

final readonly class DisbursementPrintQuery
{
    public const int VOUCHER_MIN_ROWS = 8;

    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private DocumentPrintContext $context,
        private VoucherListQuery $vouchers,
        private FeatureFlags $features,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function voucher(Voucher $voucher): array
    {
        $loaded = $this->vouchers->find($voucher->id)->load(['items.account:id,code,name', 'activeCheck.bankAccount:id,account_number,depository_bank', 'preparer:id,name']);
        $check = $loaded->activeCheck;

        return [
            'header' => $this->context->header(),
            'voucher' => (new VoucherResource($loaded))->resolve(),
            'check' => $check === null ? null : [
                'check_number' => $check->check_number,
                'check_date' => $check->check_date->format(self::DATE_FORMAT),
                'bank' => $check->bankAccount === null ? null : "{$check->bankAccount->depository_bank} · {$check->bankAccount->account_number}",
            ],
            'amountInWords' => $this->words($loaded->items_total ?? Centavos::toDecimal(0)),
            'signatories' => [
                'treasurer' => $loaded->treasurer_name,
                'bookkeeper' => $loaded->bookkeeper_name,
                'punong_barangay' => $loaded->punong_barangay_name,
                'head_of_appropriations' => $loaded->head_of_appropriations_name,
            ],
            'minRows' => self::VOUCHER_MIN_ROWS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function check(Check $check): array
    {
        return [
            'check' => [
                'id' => $check->id,
                'check_number' => $check->check_number,
                'payee_name' => $check->payee_name,
                'check_date' => $check->check_date->format(self::DATE_FORMAT),
                'amount' => $check->amount,
                'amount_in_words' => $this->words($check->amount),
                'print_count' => $check->print_count,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function liquidation(Liquidation $liquidation): array
    {
        $liquidation->load(['voucher', 'items.voucherItem:id,description,amount', 'clearer:id,name']);
        $voucher = $liquidation->voucher;
        $descriptions = [];
        foreach ($liquidation->items as $item) {
            $voucherItem = $item->voucherItem;
            if ($voucherItem instanceof VoucherItem) {
                $descriptions[$item->voucher_item_id] = [
                    'description' => $voucherItem->description,
                    'original_in_words' => $this->words($voucherItem->amount),
                ];
            }
        }

        return [
            'header' => $this->context->header(),
            'liquidation' => (new LiquidationResource($liquidation))->resolve(),
            'voucher' => $voucher === null ? null : [
                'voucher_number' => $voucher->voucher_number,
                'voucher_date' => $voucher->voucher_date->format(self::DATE_FORMAT),
                'payee_name' => $voucher->payee_name,
                'responsibility_center' => $voucher->responsibility_center,
            ],
            'descriptions' => $descriptions,
            'signatories' => [
                'treasurer' => $voucher?->treasurer_name,
                'punong_barangay' => $voucher?->punong_barangay_name,
            ],
        ];
    }

    private function words(string $amount): string
    {
        return AmountInWords::fromDecimal($amount, $this->features->enabled(FeatureFlag::AmountInWordsUppercase));
    }
}
