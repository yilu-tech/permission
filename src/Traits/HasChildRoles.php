<?php

namespace YiluTech\Permission\Traits;

use YiluTech\Permission\Util;
use YiluTech\Permission\Models\Role;

trait HasChildRoles
{
    protected $MAX_LEVEL = 3;

    /**
     * @return \Illuminate\Support\Collection
     */
    public function childRoles()
    {
        return Util::array_get($this->relations, 'childRoles', function () {
            return $this->hasChild()
                ? Role::query()->join('role_has_roles', 'roles.id', 'child_id')->where('role_id', $this->id)->get()
                : collect([]);
        });
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function parentRoles()
    {
        return Util::array_get($this->relations, 'parentRoles', function () {
            return $this->getLevel() < $this->MAX_LEVEL
                ? Role::query()->join('role_has_roles', 'roles.id', 'child_id')->where('role_id', $this->id)->get()
                : collect([]);
        });
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function extendPermissions()
    {
        return Util::array_get($this->relations, 'extendPermissions', function () {
            return $this->childRoles()->flatMap(function ($role) {
                return $role->extendPermissions();
            })->unique('id');
        });
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function allChildRoles()
    {
        $children = $this->childRoles();
        return $children->merge($children->flatMap(function ($role) {
            return $role->allChildRoles();
        }));
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function allParentRoles()
    {
        $parents = $this->parentRoles();
        return $parents->merge($parents->flatMap(function ($role) {
            return $role->allParentRoles();
        }));
    }

    public function hasChildRole($role): bool
    {
        return $this->allChildRoles()->contains('id', $this->getStoredRole($role)->id);
    }

    public function hasAnyChildRoles($roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasChildRole($role)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllChildRoles($roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasChildRole($role)) {
                return false;
            }
        }
        return true;
    }

    public function giveChildRoleTo($roles)
    {
        collect($roles)->map(function ($role) {
            return $this->getStoredRole($role);
        })->unique('id')->each(function ($role) {
            if ($role->id === $this->id) {
                throw new \Exception('can not extend self');
            }
            if ($role->isAdministrator()) {
                throw new \Exception('can not extend administrator');
            }
            if ($this->hasChildRole($role)) {
                throw new \Exception('role already exists');
            }
            if ($role->hasChildRole($this)) {
                throw new \Exception('can not extend parent');
            }
            if ($role->getLevel() >= $this->MAX_LEVEL) {
                throw new \Exception('can not extend role more than 3 level');
            }
        })->each(function ($role) {
            \DB::table('role_has_roles')->insert(['role_id' => $this->id, 'child_id' => $role->id]);
            $this->childRoles()->push($role);
        });
        return $this->unsetRelation('extendPermissions');
    }

    public function syncChildRoles($roles)
    {
        return $this->revokeChildRoleTo()->giveChildRoleTo($roles);
    }

    public function revokeChildRoleTo($roles = null)
    {
        $query = \DB::table('role_has_roles')->where('role_id', $this->id);
        if ($roles) {
            $query->whereIn('child_id', collect($roles)->pluck('id'));
        }
        $query->delete();

        $childRoles = $roles ? $this->childRoles()->diffUsing($roles, function ($a, $b) {
            return $a->id == $b->id ? 0 : -1;
        }) : collect([]);
        return $this->setRelation('childRoles', $childRoles)->unsetRelation('extendPermissions');
    }

    public function getLevel()
    {
        if (!$this->hasChild()) {
            return 1;
        }
        return $this->childRoles()->reduce(function ($level, $role) {
            return max($level, $role->getLevel() + 1);
        }, 1);
    }

    protected function hasChild()
    {
        return $this->getOriginal('child_length', 0) > 0;
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
}
