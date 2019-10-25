<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use YiluTech\Permission\Helper\RoleGroup;
use YiluTech\Permission\Traits\HasChildRoles;
use YiluTech\Permission\Traits\HasPermissions;

class Role extends Model
{
    use HasPermissions, HasChildRoles;

    protected $table = 'roles';

    protected $fillable = ['id', 'name', 'alias', 'group', 'status', 'config', 'description'];

    protected $casts = [
        'config' => 'json',
    ];

    public static function findById(int $id, $group = false)
    {
        return static::group($group)->find($id);
    }

    public static function findByName(string $name, $group = false)
    {
        return static::group($group)->where('alias', $name)->first();
    }

    public static function status($status, $group = false)
    {
        return static::group($group)->where('roles.status', '&', $status);
    }

    public static function group($group)
    {
        return RoleGroup::bindQuery(static::query(), $group, 'roles.group');
    }

    public function isAdministrator()
    {
        return $this->status & RS_ADMIN;
    }

    public function groupInfo($name = null)
    {
        $info = RoleGroup::parse($this->getAttribute('group'));
        if ($info['scope'] === null) {
            $info['scope'] = RoleGroup::scope($info['key']);
        }
        return $name ? $info[$name] : $info;
    }

    public function users()
    {
        return $this->belongsToMany(config('permission.user.model'), 'user_has_roles', 'role_id', 'user_id');
    }
}
