<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\SmsTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $title
 * @property string $body
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SmsTemplate extends Model
{
    /** @use HasFactory<SmsTemplateFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['title', 'body'];
}
