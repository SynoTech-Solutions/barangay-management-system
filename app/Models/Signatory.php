<?php

namespace App\Models;

use App\Enums\SignatoryOffice;
use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property SignatoryOffice $office
 * @property string $name
 * @property string|null $title
 * @property string|null $signature_path
 */
class Signatory extends Model
{
    use BelongsToBarangay;

    protected $fillable = ['office', 'name', 'title', 'signature_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['office' => SignatoryOffice::class];
    }

    /**
     * @return Collection<string, self>
     */
    public static function forOffices(SignatoryOffice ...$offices): Collection
    {
        return self::query()
            ->whereIn('office', $offices)
            ->get()
            ->keyBy(fn (self $signatory): string => $signatory->office->value);
    }
}
