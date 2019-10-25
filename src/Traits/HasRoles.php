<?php

namespace YiluTech\Permission\Traits;

use Illuminate\Support\Facades\Auth;
use YiluTech\Permission\Helper\Helper;
use YiluTech\Permission\Helper\RoleGroup;
use YiluTech\Permission\PermissionCache;
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
        $roles = Helper::array_get($this->relations, 'roles', function () {
            return \DB::table('user_has_roles')
                ->leftJoin('roles', 'roles.id', 'user_has_roles.role_id')
                ->where('user_id', '=', $this->id)
                ->get(['roles.*', 'user_has_roles.group'])
                ->map(function ($item) {
                    return new Role((array)$item);
                });
        });
        if ($group !== false) {
            $roles = $roles->filter(function ($role) use ($group) {
                return $role->group == $group;
            })->values();
        }
        return $roles;
    }

    /**
     * @param $group
     * @return \Illuminate\Support\Collection
     */
    public function permissions($group = false)
    {
        if (!isset($this->relations['permissions'])) {
            $this->relations['permissions'] = [];
        }
        $groupName = $group === false ? '0' : $group;
        return Helper::array_get($this->relations['permissions'], $groupName, function () use ($group) {
            return $this->roles($group)->flatMap(function ($role) {
                return $role->permissions();
            })->unique('id')->values();
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

    public function giveRoleTo($roles, $basics = false, $group = false, $fireEvent = true)
    {
        if ($this->checkAuthorizer()) {
            throw new \Exception('can not give role to self.');
        }

        $roles = collect($roles)->map(function ($role) use ($group) {
            return $this->getStoredRole($role, $group);
        });

        if ($basics) {
            $roles = $roles->merge(Role::status(RS_BASICS, $group)->get());
        }

        if (!$roles->count()) {
            return $this;
        }

        $roles->unique('id')->each(function ($role) use ($group) {
            if (!$this->hasRole($role, $group)) {
                $role->group = $this->makeRoleGroup($role);

                \DB::table('user_has_roles')->insert(['user_id' => $this->id, 'role_id' => $role->id, 'group' => $role->group]);

                $this->roles()->push($role);
            }
        });

        if ($fireEvent) {
            $this->clearPermissionCache()->fireModelEvent('roleChanged');
        }
        return $this->unsetRelation('permissions');
    }

    public function syncRoles($roles, $basics = false, $group = false)
    {
        return $this->revokeRoleTo(null, $group, false)->giveRoleTo($roles, $basics, $group);
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
        if ($fireEvent) {
            $this->clearPermissionCache()->fireModelEvent('roleChanged');
        }
        return $this->unsetRelation('roles')->unsetRelation('permissions');
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
        foreach ($this->roles(RoleGroup::parse($group, 'key')) as $role) {
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
        $group = RoleGroup::parse($role->group);
        if (!$group['key']) return '';
        if ($group['value'] === null) return $group['key'] . ':' . RoleGroup::value($group['key']);
        return $role->group;
    }
}
