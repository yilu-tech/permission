<?php

namespace YiluTech\Permission\Traits;

use Illuminate\Support\Facades\Auth;
use YiluTech\Permission\PermissionCache;
use YiluTech\Permission\Util;
use YiluTech\Permission\Models\Role;

trait HasRoles
{
    public static function roleChanged($callback)
    {
        static::registerModelEvent('roleChanged', $callback);
    }

    /**
     * @param  $group
     * @return \Illuminate\Support\Collection
     */
    public function roles($group = false)
    {
        return Util::array_get($this->relations, $this->getRelationKey('roles', $group), function () use ($group) {
            $query = \DB::table('user_has_roles')
                ->leftJoin('roles', 'roles.id', 'user_has_roles.role_id')
                ->where('user_id', '=', $this->id)
                ->select('roles.*', 'user_has_roles.group');
            if ($group !== false) {
                $query->where('user_has_roles.group', $group);
            }
            $basics = Role::status(RS_BASICS, $group)->get();
            return $query->get()->map(function ($item) {
                return new Role((array)$item);
            })->merge($basics);
        });
    }

    /**
     * @param $group
     * @return \Illuminate\Support\Collection
     */
    public function permissions($group = false)
    {
        return Util::array_get($this->relations, $this->getRelationKey('permissions', $group), function () use ($group) {
            return $this->relations['permissions'] = $this->roles($group)->flatMap(function ($role) {
                return $role->permissions();
            })->unique('id');
        });
    }

    public function syncPermissionCache(array $values = [])
    {
        (new PermissionCache($this))->sync($values);
        return $this;
    }

    public function clearPermissionCache()
    {
        (new PermissionCache($this))->clear();
        return $this;
    }

    public function checkAuthorizer()
    {
        return $this->id == Auth::id();
    }

    public function giveRoleTo($roles, $group = false)
    {
        if ($this->checkAuthorizer()) {
            throw new \Exception('can not give role to self.');
        }
        collect($roles)->map(function ($role) use ($group) {
            $role = $this->getStoredRole($role, $group);
            if ($role->status & RS_BASICS || $this->hasRole($role, $group)) {
                throw new \Exception("role<{$role->name}> already exists");
            }
            return $role;
        })->unique('id')->each(function ($role) {
            \DB::table('user_has_roles')->insert(['user_id' => $this->id, 'role_id' => $role->id, 'group' => $this->makeRoleGroup($role)]);
            $this->roles()->push($role);
        });

        $this->fireModelEvent('roleChanged')->clearPermissionCache();
        return $this->unsetRelation('permissions');
    }

    public function syncRoles($roles, $group = false)
    {
        return $this->revokeRoleTo(null, $group, false)->giveRoleTo($roles, $group);
    }

    public function revokeRoleTo($roles = null, $group = false, $fireEvent = true)
    {
        if ($this->checkAuthorizer()) {
            throw new \Exception('can not revoke self roles.');
        }
        $query = \DB::table('user_has_roles')->where('user_id', $this->id);
        if ($roles) {
            $query->whereIn('role_id', collect($roles)->pluck('id'));
        }
        if ($group !== false) {
            $query->where('group', $group);
        }
        $query->delete();

        $roles = $roles ? $this->roles()->diffUsing($roles, function ($a, $b) {
            return $a->id == $b->id ? 0 : -1;
        }) : collect([]);

        if ($fireEvent) {
            $this->fireModelEvent('roleChanged')->clearPermissionCache();
        }

        return $this->setRelation($this->getRelationKey('roles', $group), $roles)
            ->unsetRelation($this->getRelationKey('permissions', $group));
    }

    public function hasRole($role, $group = false): bool
    {
        return $this->roles($group)->flatMap(function ($role) {
            if (array_key_exists(HasChildRoles::class, class_uses($role))) {
                return $role->allChildRoles()->push($role);
            }
            return $role;
        })->contains('id', $this->getStoredRole($role)->id);
    }

    public function hasAnyRoles($roles, $group = false): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role, $group)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllRoles($roles = null, $group = false)
    {
        if (!$roles) {
            return method_exists($this, 'isAdministrator') && $this->isAdministrator();
        }
        foreach ($roles as $role) {
            if (!$this->hasRole($role, $group)) {
                return false;
            }
        }
        return true;
    }

    public function HasRoleGroup($group)
    {
        if ($this->hasAllRoles()) {
            return true;
        }
        foreach ($this->roles(Util::parse_role_group($group)['key']) as $role) {
            if ($role->isAdministrator()) {
                return true;
            }
        }
        return false;
    }

    protected function getStoredRole($role, $group = false)
    {
        if (is_array($role)) {
            return array_map([$this, 'getStoredRole'], $role);
        }
        if (is_numeric($role)) {
            $role = Role::findById($role, $group);
        } elseif (is_string($role)) {
            $role = Role::findByName($role, $group);
        }
        if ($role instanceof Role) {
            return $role;
        }
        throw new \Exception('role not exists');
    }

    protected function getRelationKey($key, $group)
    {
        return $group !== false ? $key . '_' . str_replace(':', '_', $group) : $key;
    }

    protected function makeRoleGroup($role)
    {
        $group = Util::parse_role_group($role->group);

        if (!$group['key']) return '';

        if ($group['value'] === null) return $group['key'] . ':' . Util::get_role_group_value($group['key']);

        return $role->group;
    }
}
