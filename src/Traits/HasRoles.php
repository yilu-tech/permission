<?php

namespace YiluTech\Permission\Traits;

use Illuminate\Support\Facades\Auth;
use YiluTech\Permission\PermissionCache;
use YiluTech\Permission\Util;
use YiluTech\Permission\Models\Role;

trait HasRoles
{
    /**
     * @param string $group
     * @return \Illuminate\Support\Collection
     */
    public function roles($group = null)
    {
        $key = $group ? 'roles_' . str_replace(':', '_', $group) : 'roles';
        return Util::array_get($this->relations, $key, function () use ($group) {
            $query = \DB::table('user_has_roles')->leftJoin('roles', 'roles.id', 'user_has_roles.role_id')
                ->where('user_id', '=', $this->id)
                ->select('roles.*', 'user_has_roles.*');
            if ($group) {
                $query->where('user_has_roles.group', $group);
            }
            return $query->get()->map(function ($item) {
                return new Role((array)$item);
            });
        });
    }

    /**
     * @param string $group
     * @return \Illuminate\Support\Collection
     */
    public function permissions($group = null)
    {
        $key = $group ? 'permissions_' . str_replace(':', '_', $group) : 'permissions';
        return Util::array_get($this->relations, $key, function () use ($group) {
            return $this->relations['permissions'] = $this->roles($group)->flatMap(function ($role) {
                return $role->permissions();
            })->unique('id');
        });
    }

    public function syncPermissionCache()
    {
        (new PermissionCache($this))->sync();
        return $this;
    }

    public function clearPermissionCache()
    {
        (new PermissionCache($this))->clear();
        return $this;
    }

    public function giveRoleTo($roles)
    {
        if ($this->id == Auth::id()) {
            throw new \Exception('can not give role to self.');
        }
        collect($roles)->map(function ($role) {
            return $this->getStoredRole($role);
        })->each(function ($role) {
            if ($this->hasRole($role)) {
                throw new \Exception('role already exists');
            }
            if (!Auth::hasUser() || !Auth::user()->hasRoleGroup($role->group)) {
                throw new \Exception('no permission operation.');
            }
        })->each(function ($role) {
            \DB::table('user_has_roles')->insert(['user_id' => $this->id, 'role_id' => $role->id, 'group' => $role->group]);
            $this->roles()->push($role);
        });
        return $this->unsetRelation('permissions');
    }

    public function syncRoles($roles)
    {
        return $this->revokeRoleTo()->giveRoleTo($roles);
    }

    public function revokeRoleTo($roles = null, $group = false)
    {
        if ($this->id == Auth::id()) {
            throw new \Exception('can not give role to self.');
        }
        $query = \DB::table('user_has_roles')->where('user_id', $this->id);
        if ($roles) {
            $query->whereIn('role_id', collect($roles)->pluck('id'));
        }
        if ($group === false) {
            $query->whereIn('role_id', collect($roles)->pluck('id'));
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
        if ($this->hasAllRole()) {
            return true;
        }
        foreach ($this->roles(Util::parse_role_group($group)['key']) as $role) {
            if ($role->isAdministrator()) {
                return true;
            }
        }
        return false;
    }

    protected function getStoredRole($role)
    {
        if (is_numeric($role)) {
            $role = Role::findById($role);
        } elseif (is_string($role)) {
            $role = Role::findByName($role);
        } elseif (is_array($role)) {
            return array_map([$this, 'getStoredRole'], $role);
        }
        if (!($role instanceof Role)) {
            throw new \Exception('role not exists');
        }
        return $role;
    }
}
