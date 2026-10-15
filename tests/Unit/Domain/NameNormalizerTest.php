<?php

namespace Tests\Unit\Domain;

use App\Domain\Residents\NameNormalizer;
use App\Domain\Residents\NameQuery;
use PHPUnit\Framework\TestCase;

class NameNormalizerTest extends TestCase
{
    public function test_trims_and_collapses_whitespace_without_inserting_letters(): void
    {
        $this->assertSame('Dela Cruz', NameNormalizer::normalize("  Dela \t  Cruz  "));
        $this->assertNull(NameNormalizer::normalize('   '));
        $this->assertNull(NameNormalizer::normalize(null));
    }

    public function test_full_name_is_last_comma_first_middle_extension(): void
    {
        $this->assertSame('Dela Cruz, Juan Santos Jr.', NameNormalizer::fullName(' Dela  Cruz', 'Juan ', 'Santos', 'Jr.'));
        $this->assertSame('Dela Cruz, Juan', NameNormalizer::fullName('Dela Cruz', 'Juan', '  ', null));
    }

    public function test_comparison_key_ignores_case_and_spacing(): void
    {
        $this->assertSame(NameNormalizer::key('DELA  cruz'), NameNormalizer::key('Dela Cruz'));
    }

    public function test_comma_queries_split_last_and_first_name(): void
    {
        $query = NameQuery::parse(' Dela Cruz ,  Juan ');

        $this->assertTrue($query->isCommaForm());
        $this->assertSame('Dela Cruz', $query->lastName);
        $this->assertSame('Juan', $query->firstName);
    }

    public function test_plain_queries_become_tokens(): void
    {
        $query = NameQuery::parse('juan  cruz');

        $this->assertFalse($query->isCommaForm());
        $this->assertSame(['juan', 'cruz'], $query->tokens);
        $this->assertTrue(NameQuery::parse('  ')->isEmpty());
    }
}
