<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use YiluTech\Permission\RedisStore;
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
            resolve(RedisStore::class)->empty($role);
        });
    }

    /**
     * @param $id
     * @param mixed $group
     * @return \Illuminate\Database\Eloquent\Collection|static|null
     */
    public static function findById($id, $group = false)
    {
        $query = static::group($group);
        return is_array($id) || $id instanceof Collection ? $query->findMany($id) : $query->find($id);
    }

    /**
     * @param $name
     * @param mixed $group
     * @return \Illuminate\Database\Eloquent\Collection|static|null
     */
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
        return RoleGroup::bindQuery(static::query(), $group);
    }

    public function isAdministrator()
    {
        return $this->status & RS_ADMIN;
    }

    public function users()
    {
        return $this->belongsToMany(config('permission.user.model'), 'user_has_roles', 'role_id', 'user_id');
    }
}
