<?php

namespace App\Services\Finance;

use App\Enums\AuditAction;
use App\Models\BankAccount;
use App\Models\User;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

final readonly class BankAccountService
{
    private const array AUDITED_ATTRIBUTES = [...BankAccount::DETAIL_ATTRIBUTES, 'is_active'];

    public function __construct(private AuditTrail $audit) {}

    /**
     * @param  array{account_number: string, account_name: string, depository_bank: string, branch_address: string|null}  $details
     */
    public function save(?BankAccount $account, array $details, User $actor): BankAccount
    {
        return DB::transaction(function () use ($account, $details, $actor): BankAccount {
            $account ??= new BankAccount;
            $before = $account->exists ? AuditSnapshot::of($account, self::AUDITED_ATTRIBUTES) : null;
            $account->fill($details);
            $account->updated_by = $actor->id;
            $account->save();

            $this->audit->record(AuditAction::BankAccountSaved, $account, $before, AuditSnapshot::of($account, self::AUDITED_ATTRIBUTES), $account->barangay_id, $actor->id);

            return $account;
        });
    }

    public function setActive(BankAccount $account, bool $active, User $actor): void
    {
        DB::transaction(function () use ($account, $active, $actor): void {
            $account->is_active = $active;
            $account->updated_by = $actor->id;
            $account->save();

            $this->audit->record($active ? AuditAction::Reactivated : AuditAction::Deactivated, $account, ['is_active' => ! $active], ['is_active' => $active], $account->barangay_id, $actor->id);
        });
    }
}
