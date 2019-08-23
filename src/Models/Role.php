<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use YiluTech\Permission\Traits\HasChildRoles;
use YiluTech\Permission\Traits\HasPermissions;
use YiluTech\Permission\Util;

class Role extends Model
{
    use HasPermissions, HasChildRoles;

    protected $table = 'roles';

    protected $fillable = ['id', 'name', 'group', 'config', 'description', 'child_length'];

    protected $casts = [
        'config' => 'json',
    ];

    public static function findById(int $id, $group = false)
    {
        if ($group === false) {
            return static::query()->find($id);
        }
        return static::query()->where('group', $group)->find($id);
    }

    public static function findByName(string $name)
    {
        return static::query()->where('name', $name)->first();
    }

    public function isAdministrator()
    {
        return $this->getAttribute('child_length') == -1;
    }

    public function groupInfo()
    {
        return Util::parse_role_group($this->getAttribute('group'));
    }

    public function users()
    {
        return $this->belongsToMany(config('permission.user.model'), 'user_has_roles', 'role_id', 'user_id');
    }
}
