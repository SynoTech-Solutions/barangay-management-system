<?php

namespace Tests\Feature\Finance;

use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Enums\VoucherStatus;
use App\Models\Check;
use App\Models\Liquidation;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesFinance;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class DisbursementAccessTest extends TestCase
{
    use CreatesFinance, CreatesResidents, RefreshDatabase;

    public function test_records_of_another_barangay_answer_not_found(): void
    {
        $this->financeDesk();
        $voucher = Voucher::factory()->create();
        $check = Check::factory()->create(['voucher_id' => null]);
        $liquidation = Liquidation::factory()->create();

        [$captain] = $this->financeDesk(SystemRole::BarangayAdmin);
        $this->assertNotSame($voucher->barangay_id, $captain->barangay_id);

        $this->actingAs($captain)->get(route('finance.vouchers.show', $voucher))->assertNotFound();
        $this->actingAs($captain)->get(route('finance.vouchers.print', $voucher))->assertNotFound();
        $this->actingAs($captain)->post(route('finance.vouchers.verify', $voucher))->assertNotFound();
        $this->actingAs($captain)->post(route('finance.vouchers.approve', $voucher))->assertNotFound();
        $this->actingAs($captain)->post(route('finance.vouchers.cancel', $voucher), ['reason' => 'x'])->assertNotFound();
        $this->actingAs($captain)->get(route('finance.vouchers.checks.create', $voucher))->assertNotFound();
        $this->actingAs($captain)->get(route('finance.checks.edit', $check))->assertNotFound();
        $this->actingAs($captain)->get(route('finance.checks.print', $check))->assertNotFound();
        $this->actingAs($captain)->post(route('finance.checks.release', $check), ['received_by' => 'x', 'confirmed' => true])->assertNotFound();
        $this->actingAs($captain)->post(route('finance.checks.cancel', $check), ['reason' => 'x'])->assertNotFound();
        $this->actingAs($captain)->get(route('finance.liquidations.create', $voucher))->assertNotFound();
        $this->actingAs($captain)->get(route('finance.liquidations.edit', $liquidation))->assertNotFound();
        $this->actingAs($captain)->get(route('finance.liquidations.print', $liquidation))->assertNotFound();
        $this->actingAs($captain)->post(route('finance.liquidations.clear', $liquidation))->assertNotFound();

        $this->actingAs($captain)->get(route('finance.vouchers.index'))->assertInertia(fn (Assert $page) => $page->has('vouchers.data', 0));
        $this->actingAs($captain)->get(route('finance.checks.index'))->assertInertia(fn (Assert $page) => $page->has('checks.data', 0));
    }

    public function test_roles_get_the_disbursement_actions_of_their_job(): void
    {
        [$clerk, $barangay] = $this->financeDesk(SystemRole::Clerk);
        $users = [
            'clerk' => $clerk,
            'treasurer' => $this->barangayUser(SystemRole::Treasurer, $barangay),
            'bookkeeper' => $this->barangayUser(SystemRole::Bookkeeper, $barangay),
            'captain' => $this->barangayUser(SystemRole::BarangayAdmin, $barangay),
            'viewer' => $this->barangayUser(SystemRole::Viewer, $barangay),
        ];
        $this->inBarangay($barangay);
        $voucher = Voucher::factory()->create();
        $check = Check::factory()->create(['voucher_id' => null]);
        $liquidation = Liquidation::factory()->create(['status' => 'cleared']);

        $expected = [
            'vouchers.create' => ['treasurer', 'captain'],
            'vouchers.verify' => ['bookkeeper', 'captain'],
            'vouchers.approve' => ['captain'],
            'vouchers.cancel' => ['treasurer', 'captain'],
            'checks.issue' => ['treasurer', 'captain'],
            'checks.release' => ['treasurer', 'captain'],
            'checks.cancel' => ['treasurer', 'captain'],
            'checks.print' => ['treasurer', 'captain'],
            'checks.direct' => ['captain'],
            'liquidations.manage' => ['treasurer', 'captain'],
            'liquidations.clear' => ['bookkeeper', 'captain'],
        ];
        $requests = [
            'vouchers.create' => fn () => $this->get(route('finance.vouchers.create')),
            'vouchers.verify' => fn () => $this->post(route('finance.vouchers.verify', $voucher)),
            'vouchers.approve' => fn () => $this->post(route('finance.vouchers.approve', $voucher)),
            'vouchers.cancel' => fn () => $this->post(route('finance.vouchers.cancel', $voucher), []),
            'checks.issue' => fn () => $this->get(route('finance.vouchers.checks.create', $voucher)),
            'checks.release' => fn () => $this->post(route('finance.checks.release', $check), []),
            'checks.cancel' => fn () => $this->post(route('finance.checks.cancel', $check), []),
            'checks.print' => fn () => $this->get(route('finance.checks.print', $check)),
            'checks.direct' => fn () => $this->get(route('finance.checks.direct.create')),
            'liquidations.manage' => fn () => $this->get(route('finance.liquidations.edit', $liquidation)),
            'liquidations.clear' => fn () => $this->post(route('finance.liquidations.clear', $liquidation)),
        ];

        foreach ($users as $name => $user) {
            foreach (['finance.vouchers.index', 'finance.checks.index', 'finance.liquidations.index'] as $list) {
                $this->actingAs($user)->get(route($list))->assertOk();
            }
            $this->actingAs($user)->get(route('finance.vouchers.show', $voucher))->assertOk();

            foreach ($requests as $ability => $request) {
                $this->actingAs($user);
                $status = $request()->getStatusCode();
                if (in_array($name, $expected[$ability], true)) {
                    $this->assertNotSame(403, $status, "{$name} should be allowed {$ability}");
                } else {
                    $this->assertSame(403, $status, "{$name} should be refused {$ability}");
                }
            }
        }

        $this->inBarangay($barangay);
        $this->assertSame(VoucherStatus::Verified, $voucher->refresh()->status);
    }

    public function test_turning_finance_off_hides_vouchers_checks_and_liquidations(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $this->turnOff(FeatureFlag::Finance, $barangay);

        foreach (['finance.vouchers.index', 'finance.vouchers.create', 'finance.checks.index', 'finance.liquidations.index'] as $route) {
            $this->actingAs($treasurer)->get(route($route))->assertNotFound();
        }
    }

    public function test_dashboard_counters_follow_permissions_and_flags(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $bookkeeper = $this->barangayUser(SystemRole::Bookkeeper, $barangay);
        $captain = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $this->inBarangay($barangay);
        Voucher::factory()->count(2)->create();
        Voucher::factory()->inStatus(VoucherStatus::Verified)->create();
        Voucher::factory()->cashAdvance()->inStatus(VoucherStatus::Released)->create();
        Voucher::factory()->inStatus(VoucherStatus::Released)->create();
        Check::factory()->count(3)->create(['voucher_id' => null]);
        Check::factory()->released()->create(['voucher_id' => null]);
        Liquidation::factory()->create();

        $counts = fn ($user): array => $this->actingAs($user)->get(route('dashboard'))->viewData('page')['props']['counters'];
        $byKey = fn (array $counters): array => collect($counters)->pluck('count', 'key')->only([
            'vouchers-for-verification', 'vouchers-for-approval', 'checks-for-release', 'unliquidated-cash-advances', 'liquidations-for-clearing',
        ])->all();

        $this->assertSame(['checks-for-release' => 3, 'unliquidated-cash-advances' => 1], $byKey($counts($treasurer)));
        $this->assertSame(['vouchers-for-verification' => 2, 'liquidations-for-clearing' => 1], $byKey($counts($bookkeeper)));
        $this->assertSame([
            'vouchers-for-verification' => 2, 'vouchers-for-approval' => 1, 'checks-for-release' => 3, 'unliquidated-cash-advances' => 1, 'liquidations-for-clearing' => 1,
        ], $byKey($counts($captain)));
    }

    public function test_dashboard_counters_hide_liquidation_work_when_the_flag_is_off(): void
    {
        [$captain, $barangay] = $this->financeDesk(SystemRole::BarangayAdmin);
        $this->turnOff(FeatureFlag::CashAdvanceLiquidation, $barangay);

        $keys = collect($this->actingAs($captain)->get(route('dashboard'))->viewData('page')['props']['counters'])->pluck('key')->all();

        $this->assertContains('vouchers-for-approval', $keys);
        $this->assertNotContains('unliquidated-cash-advances', $keys);
        $this->assertNotContains('liquidations-for-clearing', $keys);
    }
}
