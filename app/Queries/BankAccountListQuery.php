<?php

namespace App\Queries;

use App\Models\BankAccount;
use App\Support\Config\BmsSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class BankAccountListQuery
{
    public const int PRINT_LIMIT = 1000;

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, BankAccount>
     */
    public function paginate(?int $perPage): LengthAwarePaginator
    {
        return $this->builder()->paginate($this->settings->pagination->clamp($perPage))->withQueryString();
    }

    /**
     * @return Collection<int, BankAccount>
     */
    public function forPrint(): Collection
    {
        return $this->builder()->where('is_active', true)->limit(self::PRINT_LIMIT)->get();
    }

    /**
     * @return Builder<BankAccount>
     */
    private function builder(): Builder
    {
        return BankAccount::query()->orderByDesc('is_active')->orderBy('depository_bank')->orderBy('account_name');
    }
}
