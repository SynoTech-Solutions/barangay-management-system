<?php

namespace Tests\Feature\Sms;

use App\Enums\FeatureFlag;
use App\Enums\FlagSource;
use App\Enums\SystemRole;
use App\Models\SmsGroup;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\Features\FeatureFlags;
use App\Support\Config\BmsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesSms;
use Tests\TestCase;

class SmsAccessTest extends TestCase
{
    use CreatesSms, RefreshDatabase;

    /**
     * @return array<string, array{0: SystemRole, 1: bool, 2: bool, 3: bool, 4: bool, 5: bool}>
     */
    public static function roles(): array
    {
        return [
            'barangay admin' => [SystemRole::BarangayAdmin, true, true, true, true, true],
            'secretary' => [SystemRole::Secretary, true, true, true, true, true],
            'clerk' => [SystemRole::Clerk, true, false, false, false, false],
            'treasurer' => [SystemRole::Treasurer, true, false, false, false, false],
            'viewer' => [SystemRole::Viewer, true, false, false, false, false],
        ];
    }

    #[DataProvider('roles')]
    public function test_permission_matrix(SystemRole $role, bool $view, bool $send, bool $cancel, bool $groups, bool $templates): void
    {
        [$user] = $this->smsDesk($role);
        $this->fakeGateway();
        $message = SmsMessage::factory()->create();
        $expect = fn (bool $allowed): int => $allowed ? 200 : 403;

        $this->actingAs($user)->get(route('sms.index'))->assertStatus($expect($view));
        $this->actingAs($user)->get(route('sms.show', $message))->assertStatus($expect($view));
        $this->actingAs($user)->get(route('sms.create'))->assertStatus($expect($send));
        $this->actingAs($user)->get(route('sms.groups.index'))->assertStatus($expect($groups));
        $this->actingAs($user)->get(route('sms.templates.index'))->assertStatus($expect($templates));
        $this->actingAs($user)->postJson(route('sms.cancel'), ['ids' => [$message->id]])->assertStatus($cancel ? 302 : 403);
        $this->actingAs($user)->postJson(route('sms.store'), ['numbers' => '09170000001', 'body' => 'Hi'])->assertStatus($send ? 302 : 403);
    }

    public function test_the_module_is_off_by_default_and_every_route_answers_404(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $group = SmsGroup::factory()->create();
        $message = SmsMessage::factory()->create();

        foreach ([
            route('sms.index'),
            route('sms.show', $message),
            route('sms.create'),
            route('sms.groups.index'),
            route('sms.groups.show', $group),
            route('sms.templates.index'),
            route('sms.residents.send.create', ['scope' => 'filtered']),
        ] as $url) {
            $this->actingAs($secretary)->get($url)->assertNotFound();
        }
        $this->actingAs($secretary)->postJson(route('sms.store'), ['numbers' => '09170000001', 'body' => 'Hi'])->assertNotFound();
        $this->inBarangay($barangay);
        $this->assertSame(1, SmsMessage::query()->count());
    }

    public function test_each_dependent_flag_hides_only_its_screens(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $this->turnOff(FeatureFlag::SmsGroups, $barangay);
        $this->turnOff(FeatureFlag::SmsTemplates, $barangay);
        $this->turnOff(FeatureFlag::SmsFromResidentLists, $barangay);

        $this->actingAs($secretary)->get(route('sms.index'))->assertOk();
        $this->actingAs($secretary)->get(route('sms.groups.index'))->assertNotFound();
        $this->actingAs($secretary)->get(route('sms.templates.index'))->assertNotFound();
        $this->actingAs($secretary)->get(route('sms.residents.send.create', ['scope' => 'filtered']))->assertNotFound();
    }

    public function test_forcing_sms_off_disables_every_dependent_flag(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        config(['bms.features.forced_off' => [FeatureFlag::Sms->value]]);
        $this->app->forgetInstance(BmsSettings::class);
        $this->app->forgetInstance(FeatureFlags::class);
        $features = app(FeatureFlags::class);

        $this->assertSame(FlagSource::ForcedOff, $features->state(FeatureFlag::Sms, $barangay)->source);
        foreach ([FeatureFlag::SmsGroups, FeatureFlag::SmsTemplates, FeatureFlag::SmsFromResidentLists] as $dependent) {
            $state = $features->state($dependent, $barangay);
            $this->assertFalse($state->enabled, $dependent->value);
            $this->assertSame(FlagSource::MissingDependency, $state->source);
        }

        $this->actingAs($secretary)->get(route('sms.index'))->assertNotFound();
        $this->actingAs($secretary)->get(route('sms.groups.index'))->assertNotFound();
        $this->actingAs($secretary)->get(route('sms.templates.index'))->assertNotFound();
    }

    public function test_records_of_another_barangay_answer_404(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        [, $other] = $this->smsDesk();
        $this->inBarangay($other);
        $message = SmsMessage::factory()->create();
        $group = SmsGroup::factory()->create();
        $template = SmsTemplate::factory()->create();
        $this->inBarangay($barangay);

        $this->actingAs($secretary)->get(route('sms.show', $message))->assertNotFound();
        $this->actingAs($secretary)->get(route('sms.groups.show', $group))->assertNotFound();
        $this->actingAs($secretary)->get(route('sms.groups.message.create', $group))->assertNotFound();
        $this->actingAs($secretary)->put(route('sms.templates.update', $template), ['title' => 'X', 'body' => 'Y'])->assertNotFound();
        $this->actingAs($secretary)->delete(route('sms.templates.destroy', $template))->assertNotFound();
        $this->actingAs($secretary)->postJson(route('sms.cancel'), ['ids' => [$message->id]])->assertJsonValidationErrors('ids.0');
        $this->actingAs($secretary)->get(route('sms.index'))->assertInertia(fn (Assert $page) => $page->has('messages.data', 0));
    }

    public function test_dashboard_counts_queued_and_failed_messages_when_allowed(): void
    {
        [$clerk, $barangay] = $this->smsDesk(SystemRole::Clerk);
        SmsMessage::factory()->count(2)->create();
        SmsMessage::factory()->sending()->create();
        SmsMessage::factory()->failed()->create();
        SmsMessage::factory()->sent()->create();

        $this->actingAs($clerk)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('counters', fn ($counters) => collect($counters)->whereIn('key', ['sms-queued', 'sms-failed'])->pluck('count', 'key')->all() === ['sms-queued' => 3, 'sms-failed' => 1]));

        $this->turnOff(FeatureFlag::Sms, $barangay);
        $this->app->forgetInstance(FeatureFlags::class);
        $this->actingAs($clerk)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('counters', fn ($counters) => collect($counters)->whereIn('key', ['sms-queued', 'sms-failed'])->isEmpty()));
    }
}
