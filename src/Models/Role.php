<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use YiluTech\Permission\CacheManager;
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

    protected static function boot()
    {
        parent::boot();
        static::deleted(function ($role) {
            resolve(CacheManager::class)->empty($role);
        });
    }

    public static function findById($id, $group = false)
    {
        $query = static::group($group);
        return is_array($id) || $id instanceof Collection ? $query->findMany($id) : $query->find($id);
    }

    public static function findByName($name, $group = false)
    {
        $query = static::group($group);
        return is_array($name) || $name instanceof Collection
            ? $query->whereIn('alias', $name)->get()
            : $query->where('alias', $name)->first();
    }

    public static function status($status, $group = false)
    {
        return static::group($group)->where('roles.status', '&', $status);
    }

    public static function group($group)
    {
        if ($group === false) {
            return static::query();
        }
        $group = RoleGroup::parse($group);
        if (!$group['key']) {
            return static::query()->whereNull('group');
        }
        if ($group['value'] === null) {
            return static::query()->where('group', $group['key']);
        }
        return static::query()->where(function ($query) use ($group) {
            $query->where('group', $group['key'])->orWhere('group', $group['key'] . ':' . $group['value']);
        });
    }

    public function isAdministrator()
    {
        return $this->status & RS_ADMIN;
    }

    public function groupInfo($name = null)
    {
        $info = RoleGroup::parse($this->getAttributeFromArray('group'));
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
