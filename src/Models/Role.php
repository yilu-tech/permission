<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use YiluTech\Permission\Traits\HasChildRoles;
use YiluTech\Permission\Traits\HasPermissions;

class Role extends Model
{
    use HasPermissions, HasChildRoles;

    protected $table = 'roles';

    protected $fillable = ['id', 'name', 'group', 'parent_group', 'config', 'description', 'child_length'];

    protected $casts = [
        'config' => 'json',
    ];

    public static function findById(int $id)
    {
        return static::query()->where('id', $id)->first();
    }

    public static function findByName(string $name)
    {
        return static::query()->where('name', $name)->first();
    }

    public function users()
    {
        return $this->belongsToMany(config('permission.user.model'), 'user_has_roles', 'role_id', 'user_id');
    }

    public function includePermissions()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }
}
