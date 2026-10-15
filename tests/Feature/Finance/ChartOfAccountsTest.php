<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountKind;
use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesFinance;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class ChartOfAccountsTest extends TestCase
{
    use CreatesFinance, CreatesResidents, RefreshDatabase;

    public function test_the_standard_chart_is_installed_once_and_shown_as_a_tree(): void
    {
        [$bookkeeper, $barangay] = $this->financeDesk(SystemRole::Bookkeeper);
        $this->installChart($barangay);
        $count = Account::query()->count();
        $this->installChart($barangay);

        $this->assertSame($count, Account::query()->count());
        $this->assertTrue(Account::query()->where('is_locked', false)->doesntExist());

        $this->actingAs($bookkeeper)->get(route('finance.accounts.index'))->assertInertia(fn (Assert $page) => $page
            ->component('finance/accounts/Index')
            ->where('accounts.0.code', '1')
            ->where('accounts.0.kind', 'group')
            ->where('accounts.0.is_posting', false)
            ->where('accounts.1.code', '1-01')
            ->where('accounts.1.is_posting', true)
            ->where('canEditLocked', false));
    }

    public function test_groups_titles_and_subsidiaries_are_added_with_derived_codes_and_levels(): void
    {
        [$bookkeeper] = $this->financeDesk(SystemRole::Bookkeeper);

        $this->actingAs($bookkeeper)->post(route('finance.accounts.store'), ['kind' => 'group', 'code' => '9', 'name' => 'Sample Group', 'group_type' => 'expense'])->assertSessionHasNoErrors();
        $group = $this->account('9');
        $this->actingAs($bookkeeper)->post(route('finance.accounts.store'), ['kind' => 'title', 'code' => '9-01', 'name' => 'Sample Title', 'parent_id' => $group->id])->assertSessionHasNoErrors();
        $title = $this->account('9-01');
        $this->actingAs($bookkeeper)->post(route('finance.accounts.store'), ['kind' => 'subsidiary', 'series' => '05', 'name' => 'Sample Item', 'parent_id' => $title->id])->assertSessionHasNoErrors();
        $this->actingAs($bookkeeper)->post(route('finance.accounts.store'), ['kind' => 'subsidiary', 'series' => '05', 'name' => 'Again', 'parent_id' => $title->id])->assertSessionHasErrors('series');
        $this->actingAs($bookkeeper)->postJson(route('finance.accounts.store'), ['kind' => 'title', 'code' => '9-02', 'name' => 'Bad parent', 'parent_id' => $this->account('9-01-05')->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'account_parent_invalid');

        $item = $this->account('9-01-05');
        $this->assertSame(AccountKind::Subsidiary, $item->kind);
        $this->assertSame(2, $item->level);
        $this->assertSame($group->id, $item->group_id);
        $this->assertFalse($item->is_locked);
        $this->assertSame(3, AuditEvent::query()->where('action', AuditAction::AccountSaved)->count());
    }

    public function test_subsidiaries_cannot_be_added_under_accounts_with_budget_amounts(): void
    {
        [$bookkeeper, $barangay] = $this->financeDesk(SystemRole::Bookkeeper);
        $this->installChart($barangay);
        $title = $this->account('1-01');
        BudgetLine::factory()->create(['account_id' => $title->id, 'budget_version_id' => BudgetVersion::factory()]);

        $this->actingAs($bookkeeper)->postJson(route('finance.accounts.store'), ['kind' => 'subsidiary', 'series' => '01', 'name' => 'Split', 'parent_id' => $title->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'account_has_budget_lines');
    }

    public function test_locked_accounts_need_both_the_flag_and_the_permission(): void
    {
        [$bookkeeper, $barangay] = $this->financeDesk(SystemRole::Bookkeeper);
        $captain = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $this->installChart($barangay);
        $locked = $this->account('2-02');

        $this->actingAs($bookkeeper)->putJson(route('finance.accounts.update', $locked), ['name' => 'Renamed'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'account_locked');
        $this->actingAs($captain)->putJson(route('finance.accounts.update', $locked), ['name' => 'Renamed'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'account_locked');
        $this->actingAs($captain)->deleteJson(route('finance.accounts.destroy', $locked))
            ->assertUnprocessable()->assertJsonPath('error.code', 'account_locked');

        $this->inBarangay($barangay);
        $this->assertSame('Maintenance and Other Operating Expenses', $locked->refresh()->name);
    }

    public function test_locked_accounts_are_editable_by_holders_of_the_permission_when_the_flag_is_on(): void
    {
        [$bookkeeper, $barangay] = $this->financeDesk(SystemRole::Bookkeeper);
        $captain = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $this->installChart($barangay);
        $this->turnOn(FeatureFlag::GlLockedAccountEdit, $barangay);
        $locked = $this->account('2-02');

        $this->actingAs($captain)->put(route('finance.accounts.update', $locked), ['name' => 'Operating Expenses'])->assertSessionHasNoErrors();
        $this->actingAs($bookkeeper)->putJson(route('finance.accounts.update', $locked), ['name' => 'Again'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'account_locked');
        $this->actingAs($captain)->deleteJson(route('finance.accounts.destroy', $locked))
            ->assertUnprocessable()->assertJsonPath('error.code', 'account_locked');

        $this->inBarangay($barangay);
        $this->assertSame('Operating Expenses', $locked->refresh()->name);
    }

    public function test_removing_a_referenced_account_deactivates_it_and_unreferenced_ones_are_deleted(): void
    {
        [$bookkeeper] = $this->financeDesk(SystemRole::Bookkeeper);
        $group = Account::factory()->create();
        $withChild = Account::factory()->under($group)->create();
        Account::factory()->under($withChild, AccountKind::Subsidiary)->create();
        $budgeted = Account::factory()->under($group)->create();
        BudgetLine::factory()->create(['account_id' => $budgeted->id, 'budget_version_id' => BudgetVersion::factory()]);
        $unused = Account::factory()->under($group)->create();

        foreach ([$withChild, $budgeted, $unused] as $account) {
            $this->actingAs($bookkeeper)->delete(route('finance.accounts.destroy', $account))->assertSessionHasNoErrors();
        }

        $this->assertFalse($withChild->refresh()->is_active);
        $this->assertFalse($budgeted->refresh()->is_active);
        $this->assertNull(Account::query()->find($unused->id));
        $this->assertSame(2, AuditEvent::query()->where('action', AuditAction::Deactivated)->count());
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::Deleted)->count());

        $this->actingAs($bookkeeper)->post(route('finance.accounts.reactivate', $budgeted))->assertSessionHasNoErrors();
        $this->assertTrue($budgeted->refresh()->is_active);
    }
}
