<?php

namespace YiluTech\Permission\Traits;

use YiluTech\Permission\Util;
use YiluTech\Permission\Models\Permission;

trait HasPermissions
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function permissions()
    {
        return Util::array_get($this->relations, 'permissions', function () {
            $permissions = $this->includePermissions();

            if (array_key_exists(HasChildRoles::class, class_uses($this))) {
                $permissions = $permissions->merge($this->childRoles()->flatMap(function ($role) {
                    return $role->permissions();
                }))->unique('id');
            }

            return $permissions;
        });
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function includePermissions()
    {
        return Util::array_get($this->relations, 'includePermissions', function () {
            return Permission::query()->join('role_has_permissions', 'id', '=', 'permission_id')
                ->select('permissions.*', 'config')
                ->where('role_id', '=', $this->id)
                ->get();
        });
    }

    public function hasPermission($permission): bool
    {
        return $this->permissions()->contains('id', $this->getStoredPermission($permission)->id);
    }

    public function hasAnyPermission($permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllPermissions($permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    public function syncPermissions($permissions)
    {
        return $this->revokePermissionTo()->givePermissionTo($permissions);
    }

    public function givePermissionTo($permissions)
    {
        collect($permissions)->map(function ($permission) {
            return $this->getStoredPermission($permission);
        })->each(function ($permission) {
            if ($this->hasPermission($permission)) {
                throw new \Exception('permission already exists');
            }
        })->each(function ($permission) {
            \DB::table('role_has_permissions')->insert([
                'role_id' => $this->id,
                'permission_id' => $permission->id
            ]);
            $this->includePermissions()->push($permission);
        });
        return $this->unsetRelation('permissions');
    }

    /**
     * @param null $permission
     * @return static
     */
    public function revokePermissionTo($permission = null)
    {
        $query = \DB::table('role_has_permissions')->where('role_id', $this->id);
        if ($permission) {
            $query->whereIn('permission_id', collect($permission)->pluck('id'));
        }
        $query->delete();

        $this->relations['includePermissions'] = $permission
            ? $this->includePermissions()->diffUsing($permission, function ($a, $b) {
                return $a->id == $b->id ? 0 : -1;
            }) : [];

        $childRoles = $permission ? $this->includePermissions()->diffUsing($permission, function ($a, $b) {
            return $a->id == $b->id ? 0 : -1;
        }) : collect([]);
        return $this->setRelation('includePermissions', $childRoles)->unsetRelation('permissions');
    }

    protected function getStoredPermission($permissions)
    {
        if (is_numeric($permissions)) {
            $permissions = Permission::findById($permissions);
        } elseif (is_string($permissions)) {
            $permissions = Permission::findByName($permissions);
        }
        if (!($permissions instanceof Permission)) {
            throw new \Exception('permission not exists');
        }
        if (is_array($permissions)) {
            return array_map([$this, 'getStoredPermission'], $permissions);
        }
        return $permissions;
    }
}
