<?php

namespace Tests\Unit\Domain;

use App\Domain\Features\FlagCatalog;
use App\Enums\FeatureFlag;
use PHPUnit\Framework\TestCase;

class FlagCatalogTest extends TestCase
{
    public function test_release_flags_have_a_removal_date(): void
    {
        foreach (FlagCatalog::all() as $definition) {
            if ($definition->kind->requiresRemovalDate()) {
                $this->assertNotNull($definition->removeAfter, "{$definition->flag->value} needs a removal date");
            }
        }

        $this->addToAssertionCount(1);
    }

    public function test_dependencies_are_acyclic(): void
    {
        foreach (FeatureFlag::cases() as $flag) {
            $this->assertFalse($this->reaches($flag, $flag, []), "{$flag->value} depends on itself");
        }
    }

    /**
     * @param  list<string>  $seen
     */
    private function reaches(FeatureFlag $from, FeatureFlag $target, array $seen): bool
    {
        foreach (FlagCatalog::definition($from)->requires as $dependency) {
            if ($dependency === $target) {
                return true;
            }
            if (in_array($dependency->value, $seen, true)) {
                continue;
            }
            if ($this->reaches($dependency, $target, [...$seen, $dependency->value])) {
                return true;
            }
        }

        return false;
    }
}
