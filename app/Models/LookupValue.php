<?php

namespace App\Models;

use App\Enums\LookupType;
use Database\Factories\LookupValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $barangay_id
 * @property LookupType $type
 * @property string $label
 * @property int $sort_order
 * @property bool $is_active
 */
class LookupValue extends Model
{
    /** @use HasFactory<LookupValueFactory> */
    use HasFactory;

    protected $fillable = ['barangay_id', 'type', 'label', 'sort_order', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LookupType::class,
            'is_active' => 'boolean',
        ];
    }

    public function isGlobal(): bool
    {
        return $this->barangay_id === null;
    }
}
