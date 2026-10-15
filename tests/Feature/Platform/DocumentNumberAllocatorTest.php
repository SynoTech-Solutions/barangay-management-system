<?php

namespace Tests\Feature\Platform;

use App\Enums\DocumentKind;
use App\Models\Barangay;
use App\Services\Numbering\DocumentNumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberAllocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_are_sequential_per_barangay_and_year(): void
    {
        [$first, $second] = Barangay::factory()->count(2)->create()->all();
        $allocator = app(DocumentNumberAllocator::class);

        $this->inBarangay($first);
        $this->assertSame('BC-2026-000001', $allocator->next(DocumentKind::BarangayClearance, 2026));
        $this->assertSame('BC-2026-000002', $allocator->next(DocumentKind::BarangayClearance, 2026));
        $this->assertSame('BC-2027-000001', $allocator->next(DocumentKind::BarangayClearance, 2027));

        $this->inBarangay($second);
        $this->assertSame('BC-2026-000001', $allocator->next(DocumentKind::BarangayClearance, 2026));
    }

    public function test_continuous_numbers_ignore_the_year(): void
    {
        $this->inBarangay(Barangay::factory()->create());
        $allocator = app(DocumentNumberAllocator::class);

        $allocator->next(DocumentKind::Resident, 2026);

        $this->assertSame('RES-000002', $allocator->next(DocumentKind::Resident, 2027));
    }
}
