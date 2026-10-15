<?php

namespace Tests\Feature\Sms;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Models\AuditEvent;
use App\Models\SmsGroup;
use App\Models\SmsTemplate;
use App\Services\Features\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSms;
use Tests\TestCase;

class SmsTemplateTest extends TestCase
{
    use CreatesSms, RefreshDatabase;

    public function test_templates_are_created_edited_and_deleted_with_unique_titles(): void
    {
        [$secretary, $barangay] = $this->smsDesk();

        $this->actingAs($secretary)->post(route('sms.templates.store'), ['title' => 'Assembly', 'body' => "Barangay assembly\u{0007} on Saturday."])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('sms.templates.store'), ['title' => 'assembly', 'body' => 'Other'])->assertSessionHasErrors('title');
        $this->actingAs($secretary)->post(route('sms.templates.store'), ['title' => 'Long', 'body' => str_repeat('a', 766)])->assertSessionHasErrors('body');

        $this->inBarangay($barangay);
        $template = SmsTemplate::query()->sole();
        $this->assertSame('Barangay assembly on Saturday.', $template->body);

        $this->actingAs($secretary)->put(route('sms.templates.update', $template), ['title' => 'Assembly', 'body' => 'Moved to Sunday.'])->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSame('Moved to Sunday.', $template->refresh()->body);

        $this->actingAs($secretary)->get(route('sms.templates.index'))->assertInertia(fn (Assert $page) => $page
            ->component('sms/templates/Index')
            ->where('templates.0.title', 'Assembly'));

        $this->actingAs($secretary)->delete(route('sms.templates.destroy', $template))->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSame(0, SmsTemplate::query()->count());
        $this->assertSame(2, AuditEvent::query()->where('action', AuditAction::SmsTemplateSaved)->count());
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::SmsTemplateDeleted)->exists());
        $this->assertStringNotContainsString('Sunday', (string) json_encode(AuditEvent::query()->pluck('changes')));
    }

    public function test_templates_prefill_the_compose_screens_only_while_the_flag_is_on(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $template = SmsTemplate::factory()->create(['title' => 'Clean-up', 'body' => 'Clean-up drive on Sunday at 6 AM.']);
        $group = SmsGroup::factory()->create();

        $this->actingAs($secretary)->get(route('sms.create'))->assertInertia(fn (Assert $page) => $page
            ->where('templates', [['id' => $template->id, 'title' => 'Clean-up', 'body' => 'Clean-up drive on Sunday at 6 AM.']]));
        $this->actingAs($secretary)->get(route('sms.groups.message.create', $group))->assertInertia(fn (Assert $page) => $page
            ->has('templates', 1));
        $this->actingAs($secretary)->get(route('sms.residents.send.create', ['scope' => 'filtered']))->assertInertia(fn (Assert $page) => $page
            ->has('templates', 1));

        $this->turnOff(FeatureFlag::SmsTemplates, $barangay);
        $this->app->forgetInstance(FeatureFlags::class);
        $this->actingAs($secretary)->get(route('sms.templates.index'))->assertNotFound();
    }

    public function test_compose_screens_offer_no_templates_when_the_flag_is_off(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        SmsTemplate::factory()->create();
        $this->turnOff(FeatureFlag::SmsTemplates, $barangay);

        $this->actingAs($secretary)->get(route('sms.create'))->assertInertia(fn (Assert $page) => $page->where('templates', []));
    }
}
