<?php

namespace Tests\Feature\Finance;

use App\Domain\Audit\AuditRedactor;
use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\BankAccount;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesFinance;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class BankAccountVendorTest extends TestCase
{
    use CreatesFinance, CreatesResidents, RefreshDatabase;

    public function test_bank_accounts_are_managed_with_account_numbers_redacted_from_the_audit_trail(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $payload = ['account_number' => ' 000-1111-2222 ', 'account_name' => 'General  Fund', 'depository_bank' => 'Sample Bank'];

        $this->actingAs($treasurer)->post(route('finance.bank-accounts.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('finance.bank-accounts.store'), [...$payload, 'account_name' => 'Other'])->assertSessionHasErrors('account_number');

        $account = BankAccount::query()->sole();
        $this->assertSame('000-1111-2222', $account->account_number);
        $this->assertSame('General Fund', $account->account_name);

        $this->actingAs($treasurer)->put(route('finance.bank-accounts.update', $account), [...$payload, 'account_number' => '000-1111-3333', 'branch_address' => 'Town Center'])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('finance.bank-accounts.deactivate', $account))->assertSessionHasNoErrors();
        $this->assertFalse($account->refresh()->is_active);

        $saved = AuditEvent::query()->where('action', AuditAction::BankAccountSaved)->orderBy('id')->get();
        $this->assertCount(2, $saved);
        foreach ($saved as $event) {
            $encoded = json_encode($event->changes);
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString('1111', $encoded);
            $this->assertSame(AuditRedactor::REDACTED, $event->changes['after']['account_number']);
        }

        $this->actingAs($treasurer)->get(route('finance.bank-accounts.index'))->assertInertia(fn (Assert $page) => $page
            ->component('finance/bank-accounts/Index')
            ->where('accounts.data.0.account_number', '000-1111-3333'));
        $this->actingAs($treasurer)->get(route('finance.bank-accounts.print'))->assertInertia(fn (Assert $page) => $page
            ->component('finance/bank-accounts/ListPrint')
            ->has('accounts', 0)
            ->where('header.barangay', $barangay->name));
    }

    public function test_vendors_are_managed_with_unique_names_and_redacted_tins(): void
    {
        [$treasurer] = $this->financeDesk();
        $payload = ['name' => 'Sample  Supplies', 'address' => 'Sample Street', 'contact_number' => '0917 000 0000', 'email' => 'Orders@Example.TEST', 'tin' => '123-456-789'];

        $this->actingAs($treasurer)->post(route('finance.vendors.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('finance.vendors.store'), [...$payload, 'name' => 'sample supplies'])->assertSessionHasErrors('name');
        $this->actingAs($treasurer)->post(route('finance.vendors.store'), [...$payload, 'name' => 'Other', 'address' => ''])->assertSessionHasErrors('address');

        $vendor = Vendor::query()->sole();
        $this->assertSame('Sample Supplies', $vendor->name);
        $this->assertSame('orders@example.test', $vendor->email);
        $this->actingAs($treasurer)->put(route('finance.vendors.update', $vendor), [...$payload, 'contact_person' => 'Ana Santos'])->assertSessionHasNoErrors();
        $this->assertSame('Ana Santos', $vendor->refresh()->contact_person);

        $event = AuditEvent::query()->where('action', AuditAction::VendorSaved)->latest('id')->firstOrFail();
        $this->assertSame(AuditRedactor::REDACTED, $event->changes['after']['tin']);

        $this->actingAs($treasurer)->get(route('finance.vendors.index', ['search' => 'sample']))->assertInertia(fn (Assert $page) => $page
            ->component('finance/vendors/Index')
            ->has('vendors.data', 1));
        $this->actingAs($treasurer)->get(route('finance.vendors.print'))->assertInertia(fn (Assert $page) => $page
            ->component('finance/vendors/ListPrint')
            ->where('vendors.0.name', 'Sample Supplies'));
    }

    public function test_only_finance_roles_manage_banks_and_vendors_and_other_barangays_are_hidden(): void
    {
        [$clerk, $barangay] = $this->financeDesk(SystemRole::Clerk);
        $bookkeeper = $this->barangayUser(SystemRole::Bookkeeper, $barangay);
        $payload = ['account_number' => '1', 'account_name' => 'A', 'depository_bank' => 'B'];

        $this->actingAs($clerk)->get(route('finance.vendors.index'))->assertOk();
        $this->actingAs($clerk)->post(route('finance.bank-accounts.store'), $payload)->assertForbidden();
        $this->actingAs($bookkeeper)->post(route('finance.bank-accounts.store'), $payload)->assertForbidden();
        $this->actingAs($clerk)->post(route('finance.vendors.store'), ['name' => 'X', 'address' => 'Y', 'contact_number' => 'Z'])->assertForbidden();

        $this->financeDesk();
        $foreignBank = BankAccount::factory()->create();
        $foreignVendor = Vendor::factory()->create();
        [$treasurer] = $this->financeDesk(SystemRole::Treasurer, $barangay);

        $this->actingAs($treasurer)->put(route('finance.bank-accounts.update', $foreignBank), $payload)->assertNotFound();
        $this->actingAs($treasurer)->post(route('finance.vendors.deactivate', $foreignVendor))->assertNotFound();
    }
}
