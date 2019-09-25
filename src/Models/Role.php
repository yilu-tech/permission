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

    protected $fillable = ['id', 'name', 'alias', 'group', 'status', 'config', 'description'];

    protected $casts = [
        'config' => 'json',
    ];

    public static function findById(int $id, $group = false)
    {
        if ($group === false) {
            return static::query()->find($id);
        }
        return static::query()->where(function ($query) use ($group) {
            $query->where('group', $group)->orWhere('group', strstr($group, ':', true));
        })->find($id);
    }

    public static function findByName(string $name)
    {
        return static::query()->where('name', $name)->first();
    }

    public function isAdministrator()
    {
        return $this->status & RS_ADMIN;
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
