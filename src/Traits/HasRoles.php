<?php

namespace YiluTech\Permission\Traits;

use Illuminate\Support\Facades\Auth;
use YiluTech\Permission\PermissionCache;
use YiluTech\Permission\Util;
use YiluTech\Permission\Models\Role;

trait HasRoles
{
    protected $cacheExists = true;

    /**
     * @param  $group
     * @return \Illuminate\Support\Collection
     */
    public function roles($group = false)
    {
        return Util::array_get($this->relations, $this->getRelationKey('roles', $group), function () use ($group) {
            $query = \DB::table('user_has_roles')->leftJoin('roles', 'roles.id', 'user_has_roles.role_id')
                ->where('user_id', '=', $this->id)
                ->select('roles.*', 'user_has_roles.*');
            if ($group !== false) {
                $query->where('user_has_roles.group', $group);
            }
            return $query->get()->map(function ($item) {
                return new Role((array)$item);
            });
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
        $this->cacheExists = true;
        return $this;
    }

    public function clearPermissionCache()
    {
        if ($this->cacheExists) {
            (new PermissionCache($this))->clear();
            $this->cacheExists = false;
        }
        return $this;
    }

    public function giveRoleTo($roles, $group = false)
    {
        if ($this->id == Auth::id()) {
            throw new \Exception('can not give role to self.');
        }
        collect($roles)->map(function ($role) use ($group) {
            return $this->getStoredRole($role, $group);
        })->each(function ($role) {
            if ($this->hasRole($role)) {
                throw new \Exception('role already exists');
            }
//            if (!Auth::hasUser() || !Auth::user()->hasRoleGroup($role->group)) {
//                throw new \Exception('no permission operation.');
//            }
        })->each(function ($role) {
            \DB::table('user_has_roles')->insert(['user_id' => $this->id, 'role_id' => $role->id, 'group' => $this->makeRoleGroup($role)]);
            $this->roles()->push($role);
        });
        return $this->clearPermissionCache()->unsetRelation('permissions');
    }

    public function syncRoles($roles, $group = false)
    {
        return $this->revokeRoleTo(null, $group)->giveRoleTo($roles, $group);
    }

    public function revokeRoleTo($roles = null, $group = false)
    {
        if ($this->id == Auth::id()) {
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

        return $this->clearPermissionCache()
            ->setRelation($this->getRelationKey('roles', $group), $roles)
            ->unsetRelation($this->getRelationKey('permissions', $group));
    }

    public function hasRole($role): bool
    {
        return $this->roles()->flatMap(function ($role) {
            if (array_key_exists(HasChildRoles::class, class_uses($role))) {
                return $role->allChildRoles()->push($role);
            }
            return $role;
        })->contains('id', $this->getStoredRole($role)->id);
    }

    public function hasAnyRoles($roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllRoles($roles = null)
    {
        if (!$roles) {
            return method_exists($this, 'isAdministrator') && $this->isAdministrator();
        }
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
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
        }
        if (!($role instanceof Role) || ($group !== false && $role->group != $group)) {
            throw new \Exception('role not exists');
        }
        return $role;
    }

    protected function getRelationKey($key, $group)
    {
        return $group !== false ? $key . '_' . str_replace(':', '_', $group) : $key;
    }

    protected function makeRoleGroup($role)
    {
        $group = Util::parse_role_group($role->group);

        if (!$group['key']) return '';

        if ($group['value'] === null) return Util::get_role_group_value($group['key']);

        return $role->group;
    }
}
