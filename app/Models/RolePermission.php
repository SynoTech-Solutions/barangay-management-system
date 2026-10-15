<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $role_id
 * @property string $permission
 */
class RolePermission extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['role_id', 'permission'];
}
