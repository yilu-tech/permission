<?php

namespace YiluTech\Permission\Traits;

use YiluTech\Permission\Helper\Helper;
use YiluTech\Permission\Models\Permission;

trait HasPermissions
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function permissions()
    {
        return Helper::array_get($this->relations, 'permissions', function () {
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
        return Helper::array_get($this->relations, 'includePermissions', function () {
            if ($this->isAdministrator()) {
                return Permission::query($this->getPermissionScope())->get();
            }
            $query = \DB::table('role_has_permissions')
                ->leftJoin('permissions', 'permissions.id', 'role_has_permissions.permission_id')
                ->where('role_id', '=', $this->id);

            return Permission::query(false, null, $query)->addSelect('config')->get()->map(function ($item) {
                return new Permission((array)$item);
            });
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
                throw new \Exception("permission<{$permission->name}> already exists");
            }
        })->each(function ($permission) {
            \DB::table('role_has_permissions')->insert(['role_id' => $this->id, 'permission_id' => $permission->id]);
            $this->includePermissions()->push($permission);
        });
        return $this->unsetRelation('permissions');
    }

    /**
     * @param null $permissions
     * @return static
     */
    public function revokePermissionTo($permissions = null)
    {
        $query = \DB::table('role_has_permissions')->where('role_id', $this->id);
        if ($permissions) {
            $query->whereIn('permission_id', collect($permissions)->pluck('id'));
        }
        $query->delete();

        $this->relations['includePermissions'] = $permissions
            ? $this->includePermissions()->diffUsing($permissions, function ($a, $b) {
                return $a->id == $b->id ? 0 : -1;
            }) : collect([]);

        return $this->unsetRelation('permissions');
    }

    protected function getPermissionScope()
    {
        $info = $this->groupInfo();
        if (!$info['scope']) {
            return $info['key'];
        }
        return $info['key'] ? $info['scope'] . '.' . $info['key'] : $info['scope'];
    }

    protected function getStoredPermission($permissions)
    {
        if (is_numeric($permissions)) {
            $permissions = Permission::findById($permissions);
        } elseif (is_string($permissions)) {
            $permissions = Permission::findByName($permissions);
        } elseif (is_array($permissions)) {
            return array_map([$this, 'getStoredPermission'], $permissions);
        }
        if (!($permissions instanceof Permission)) {
            throw new \Exception('Permission not exists');
        }
        if (!in_array($this->getPermissionScope(), $permissions->scopes)) {
            throw new \Exception('Permission not in role scope');
        }
        return $permissions;
    }
}
