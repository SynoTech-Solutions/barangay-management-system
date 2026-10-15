<?php

namespace Tests\Unit\Domain;

use App\Domain\Features\FlagInputs;
use App\Domain\Features\FlagResolver;
use App\Enums\FeatureFlag;
use App\Enums\FlagSource;
use PHPUnit\Framework\TestCase;

class FlagResolverTest extends TestCase
{
    public function test_catalog_default_applies_without_overrides(): void
    {
        $state = FlagResolver::resolve(FeatureFlag::BarangayClearance, new FlagInputs);

        $this->assertTrue($state->enabled);
        $this->assertSame(FlagSource::Default, $state->source);
    }

    public function test_barangay_override_beats_global_override(): void
    {
        $state = FlagResolver::resolve(FeatureFlag::Sms, new FlagInputs(
            globalOverrides: [FeatureFlag::Sms->value => false],
            barangayOverrides: [FeatureFlag::Sms->value => true],
        ));

        $this->assertTrue($state->enabled);
        $this->assertSame(FlagSource::BarangayOverride, $state->source);
    }

    public function test_global_override_beats_default(): void
    {
        $state = FlagResolver::resolve(FeatureFlag::Sms, new FlagInputs(globalOverrides: [FeatureFlag::Sms->value => true]));

        $this->assertTrue($state->enabled);
        $this->assertSame(FlagSource::GlobalOverride, $state->source);
    }

    public function test_operator_forced_off_beats_every_override(): void
    {
        $state = FlagResolver::resolve(FeatureFlag::Sms, new FlagInputs(
            forcedOff: [FeatureFlag::Sms],
            globalOverrides: [FeatureFlag::Sms->value => true],
            barangayOverrides: [FeatureFlag::Sms->value => true],
        ));

        $this->assertFalse($state->enabled);
        $this->assertSame(FlagSource::ForcedOff, $state->source);
    }

    public function test_a_flag_is_off_while_its_dependency_is_off(): void
    {
        $state = FlagResolver::resolve(FeatureFlag::SmsGroups, new FlagInputs);

        $this->assertFalse($state->enabled);
        $this->assertSame(FlagSource::MissingDependency, $state->source);
        $this->assertSame(FeatureFlag::Sms, $state->blockedBy);
        $this->assertTrue($state->configured);
    }

    public function test_dependencies_are_followed_transitively(): void
    {
        $state = FlagResolver::resolve(FeatureFlag::LiquidationClearing, new FlagInputs(
            barangayOverrides: [FeatureFlag::Finance->value => false],
        ));

        $this->assertFalse($state->enabled);
        $this->assertSame(FeatureFlag::CashAdvanceLiquidation, $state->blockedBy);
    }

    public function test_turning_on_the_dependency_enables_the_dependent_flag(): void
    {
        $state = FlagResolver::resolve(FeatureFlag::SmsGroups, new FlagInputs(barangayOverrides: [FeatureFlag::Sms->value => true]));

        $this->assertTrue($state->enabled);
    }

    public function test_resolve_all_returns_every_flag(): void
    {
        $this->assertCount(count(FeatureFlag::cases()), FlagResolver::resolveAll(new FlagInputs));
    }
}
