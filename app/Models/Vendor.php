<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $name
 * @property string $address
 * @property string $contact_number
 * @property string|null $email
 * @property string|null $contact_person
 * @property string|null $position
 * @property string|null $tin
 * @property bool $is_active
 * @property int|null $updated_by
 */
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use BelongsToBarangay, HasFactory;

    public const array DETAIL_ATTRIBUTES = ['name', 'address', 'contact_number', 'email', 'contact_person', 'position', 'tin'];

    protected $fillable = [...self::DETAIL_ATTRIBUTES, 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
