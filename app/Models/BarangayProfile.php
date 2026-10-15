<?php

namespace App\Models;

use App\Enums\ClearanceValidityMode;
use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $barangay_id
 * @property string|null $telephone
 * @property string|null $email
 * @property string|null $id_card_ordinance_no
 * @property ClearanceValidityMode $clearance_validity_mode
 * @property int|null $clearance_validity_months
 * @property string|null $logo_path
 */
class BarangayProfile extends Model
{
    use BelongsToBarangay;

    protected $primaryKey = 'barangay_id';

    public $incrementing = false;

    protected $fillable = [
        'telephone',
        'email',
        'id_card_ordinance_no',
        'clearance_validity_mode',
        'clearance_validity_months',
        'logo_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['clearance_validity_mode' => ClearanceValidityMode::class];
    }
}
