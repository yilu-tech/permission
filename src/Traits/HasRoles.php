<?php

namespace YiluTech\Permission\Traits;

use YiluTech\Permission\IdentityUtil;
use YiluTech\Permission\Util;
use YiluTech\Permission\Models\Role;

trait HasRoles
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function roles()
    {
        return Util::array_get($this->relations, 'roles', function () {
            $query = Role::query()->join('user_has_roles', 'roles.id', '=', 'role_id')
                ->where('user_id', '=', $this->id)
                ->select('roles.*', 'user_has_roles.*');

            if (array_key_exists(HasIdentity::class, class_uses($this)) && $this->useIdentity) {
                $this->whereIdentity($query);
            }

            return $query->get();
        });
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function permissions()
    {
        return Util::array_get($this->relations, 'permissions', function () {
            return $this->relations['permissions'] = $this->roles()->flatMap(function ($role) {
                return $role->permissions();
            })->unique('id');
        });
    }

    public function syncCache()
    {
        if (array_key_exists(HasIdentity::class, class_uses($this))) {
            $cacheValues = $this->identityPermissions();
        } else {
            $cacheValues[''] = $this->permissions();
        }

        $cacheValues['sync_time'] = date('Y-m-d H:i:s');
        $cacheValues['is_administrator'] = method_exists($this, 'isAdministrator') ? $this->isAdministrator() : false;

        $prefix = $this->getCachePrefix();

        \Redis::multi();

        $this->clearCache();

        $keys = ["$prefix:keys"];

        foreach ($cacheValues as $key => $value) {
            if ($key) {
                $key = $key{0} === '@' ? substr($key, 1) : "$prefix:$key";
            } else {
                $key = $prefix;
            }
            if (is_array($value)) {
                \Redis::hmset($key, $value);
            } else {
                \Redis::set($key, $value);
            }
            $keys[] = $key;
        }

        \Redis::sadd("$prefix:keys", $keys);
        \Redis::exec();

        return $this;
    }

    public function clearCache()
    {
        $prefix = $this->getCachePrefix();

        $keys = \Redis::smembers("$prefix:keys");

        if (is_array($keys) && count($keys)) \Redis::del($keys);

        return $this;
    }

    public function getCachePrefix()
    {
        $prefix = config('permission.cache_prefix') ?: 'permission';
        return $prefix . ':' . $this->id;
    }

    public function giveRoleTo($roles)
    {
        collect($roles)->map(function ($role) {
            return $this->getStoredRole($role);
        })->each(function ($role) use ($roles) {
            if ($this->hasRole($role)) {
                throw new \Exception('role already exists');
            }
        })->each(function ($role) {
            $data = ['user_id' => $this->id, 'role_id' => $role->id];
            if (array_key_exists(HasIdentity::class, class_uses($this))) {
                $data = array_merge($data, array_combine(IdentityUtil::getScopeKeys(), $this->getIdentity()));
            }
            \DB::table('user_has_roles')->insert($data);
            $this->roles()->push($role);
        });
        return $this->unsetRelation('permissions');
    }

    public function syncRoles($roles)
    {
        return $this->revokeRoleTo()->giveRoleTo($roles);
    }

    public function revokeRoleTo($roles = null)
    {
        $query = \DB::table('user_has_roles')->where('user_id', $this->id);
        if ($roles) {
            $query->whereIn('role_id', collect($roles)->pluck('id'));
        }
        if (array_key_exists(HasIdentity::class, class_uses($this))) {
            $this->whereIdentity($query);
        }
        $query->delete();

        $roles = $roles ? $this->roles()->diffUsing($roles, function ($a, $b) {
            return $a->id == $b->id ? 0 : -1;
        }) : collect([]);
        return $this->setRelation('roles', $roles)->unsetRelation('permissions');
    }

    public function hasRole($role): bool
    {
        return $this->roles()->flatMap(function ($role) {
            if (array_key_exists(HasChildRoles::class, class_uses($this))) {
                return $role->allChildRoles();
            }
            return $role;
        })->contains('id', $this->getStoredRole($role)->id);
    }

    public function hasAnyRole($roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllRole($roles)
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        return true;
    }

    protected function getStoredRole($role)
    {
        if (is_numeric($role)) {
            $role = Role::findById($role);
        } elseif (is_string($role)) {
            $role = Role::findByName($role);
        }
        if (!($role instanceof Role)) {
            throw new \Exception('role not exists');
        }
        if (is_array($role)) {
            return array_map([$this, 'getStoredRole'], $role);
        }
        return $role;
    }
}
