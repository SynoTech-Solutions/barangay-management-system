<?php

namespace Tests\Feature\Finance;

use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\CollectionType;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFinance;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class FinanceAccessTest extends TestCase
{
    use CreatesFinance, CreatesResidents, RefreshDatabase;

    public function test_turning_finance_off_hides_every_finance_screen(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $this->turnOff(FeatureFlag::Finance, $barangay);

        foreach (['finance.accounts.index', 'finance.budgets.index', 'finance.bank-accounts.index', 'finance.vendors.index'] as $route) {
            $this->actingAs($treasurer)->get(route($route))->assertNotFound();
        }
        $this->actingAs($treasurer)->postJson(route('finance.budgets.store'), ['fiscal_year' => 2040])->assertNotFound();
    }

    public function test_roles_get_the_finance_actions_of_their_job(): void
    {
        [$clerk, $barangay] = $this->financeDesk(SystemRole::Clerk);
        $treasurer = $this->barangayUser(SystemRole::Treasurer, $barangay);
        $bookkeeper = $this->barangayUser(SystemRole::Bookkeeper, $barangay);
        $group = ['kind' => 'group', 'code' => '7', 'name' => 'Sample', 'group_type' => 'other'];

        foreach ([$clerk, $treasurer, $bookkeeper] as $user) {
            $this->actingAs($user)->get(route('finance.budgets.index'))->assertOk();
            $this->actingAs($user)->get(route('finance.accounts.index'))->assertOk();
            $this->actingAs($user)->get(route('collections.index'))->assertOk();
        }

        $this->actingAs($clerk)->post(route('finance.budgets.store'), ['fiscal_year' => 2041])->assertForbidden();
        $this->actingAs($bookkeeper)->post(route('finance.budgets.store'), ['fiscal_year' => 2041])->assertForbidden();
        $this->actingAs($treasurer)->post(route('finance.budgets.store'), ['fiscal_year' => 2041])->assertSessionHasNoErrors();

        $this->actingAs($clerk)->post(route('finance.accounts.store'), $group)->assertForbidden();
        $this->actingAs($treasurer)->post(route('finance.accounts.store'), $group)->assertForbidden();
        $this->actingAs($bookkeeper)->post(route('finance.accounts.store'), $group)->assertSessionHasNoErrors();

        $this->actingAs($clerk)->get(route('collections.create'))->assertOk();
        $this->actingAs($bookkeeper)->get(route('collections.create'))->assertForbidden();
        $this->actingAs($clerk)->get(route('collections.report.export'))->assertForbidden();
        $this->actingAs($bookkeeper)->get(route('collections.report.export'))->assertOk();
    }

    public function test_every_finance_record_of_another_barangay_answers_not_found(): void
    {
        [, $other] = $this->financeDesk();
        $account = Account::factory()->create();
        $budget = Budget::factory()->create();
        BudgetVersion::factory()->create(['budget_id' => $budget->id]);
        $bank = BankAccount::factory()->create();
        $vendor = Vendor::factory()->create();
        $type = CollectionType::factory()->create();

        [$captain] = $this->financeDesk(SystemRole::BarangayAdmin);
        $this->assertNotSame($other->id, $captain->barangay_id);

        $this->actingAs($captain)->put(route('finance.accounts.update', $account), ['name' => 'x'])->assertNotFound();
        $this->actingAs($captain)->get(route('finance.budgets.show', $budget))->assertNotFound();
        $this->actingAs($captain)->get(route('finance.budgets.summary', $budget))->assertNotFound();
        $this->actingAs($captain)->post(route('finance.budgets.approve', $budget))->assertNotFound();
        $this->actingAs($captain)->put(route('finance.bank-accounts.update', $bank), ['account_number' => '1', 'account_name' => 'a', 'depository_bank' => 'b'])->assertNotFound();
        $this->actingAs($captain)->put(route('finance.vendors.update', $vendor), ['name' => 'a', 'address' => 'b', 'contact_number' => 'c'])->assertNotFound();
        $this->actingAs($captain)->put(route('collections.types.update', $type), ['name' => 'x'])->assertNotFound();
    }
}
