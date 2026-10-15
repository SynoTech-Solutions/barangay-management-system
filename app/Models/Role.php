<?php

namespace App\Models;

use App\Enums\RoleScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $barangay_id
 * @property string $key
 * @property string|null $name
 * @property RoleScope $scope
 * @property bool $is_system
 */
class Role extends Model
{
    protected $fillable = ['barangay_id', 'key', 'name', 'scope', 'is_system'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => RoleScope::class,
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RolePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }
}
