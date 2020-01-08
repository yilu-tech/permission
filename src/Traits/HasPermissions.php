<?php

namespace YiluTech\Permission\Traits;

use YiluTech\Permission\Helper\Helper;
use YiluTech\Permission\Models\Permission;

trait HasPermissions
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissionRelation()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }

    /**
     * @param $depth
     * @return \Illuminate\Support\Collection
     */
    public function permissions($depth = INF)
    {
        $permissions = Helper::array_get($this->relations, 'permissions', function () use ($depth) {
            if ($this->isAdministrator()) {
                return Permission::query($this->getPermissionScope())->get();
            }
            return $this->permissionRelation()->withPivot('config')->get();
        });

        if ($this->isAdministrator() || $permissions->isEmpty() || !$this->hasChild() || $depth == 0) {
            return $permissions;
        }

        return $permissions->merge($this->childRoles($depth)->flatMap(function ($role) {
            return $role->permissions();
        }))->unique('id')->values();
    }

    public function hasPermission($id): bool
    {
        return $this->permissions()->contains('id', $id);
    }

    public function hasAnyPermission(array $ids): bool
    {
        return !empty(array_intersect($ids, $this->permissions()->pluck('id')->all()));
    }

    public function hasAllPermissions(array $ids = null): bool
    {
        if (!$ids) {
            return $this->isAdministrator();
        }
        return empty(array_diff($ids, $this->permissions()->pluck('id')->all()));
    }

    public function syncPermissions($permissions)
    {
        $this->writable();

        $permissions = $this->parsePermission($permissions);

        if ($permissions->isEmpty()) {
            return $this->revokePermissionTo();
        }

        $current = $this->permissions(0)->pluck('id')->all();

        $detached = array_diff($current, $permissions->pluck('id')->all());
        if (count($detached)) {
            $result = $this->revokePermissionTo($detached);
        } else {
            $result = 0;
        }

        return $result + $this->givePermissionTo($permissions);
    }

    public function givePermissionTo($permissions)
    {
        $this->writable();

        $permissions = $this->parsePermission($permissions);

        $attach = $permissions->diffUsing($this->permissions(0), function ($a, $b) {
            return $a->id - $b->id;
        });

        if ($attach->isEmpty()) {
            return 0;
        }

        $relation = $this->permissionRelation();
        foreach ($attach as $item) {
            $relation->attach($item->id);

            $item->setRelation('pivot', $relation->newExistingPivot());
            $this->relations['permissions']->push($item);
        }
        return $attach->count();
    }

    public function revokePermissionTo($permissions = null)
    {
        $this->writable();

        $result = $this->permissionRelation()->detach($permissions);
        if ($result) {
            if ($this->relationLoaded('permissions') && $permissions) {
                $this->setRelation('permissions', $this->permissions(0)->filter(function ($item) use ($permissions) {
                    if (is_array($permissions)) {
                        return !in_array($item->id, $permissions);
                    }
                    return $item->id != $permissions;
                })->values());
            }
            if ($permissions == null) {
                $this->setRelation('permissions', collect());
            }
        }
        return $result;
    }

    protected function getPermissionScope()
    {
        $info = $this->groupInfo();
        if (!$info['scope']) {
            return $info['key'];
        }
        return $info['key'] ? $info['scope'] . '.' . $info['key'] : $info['scope'];
    }

    protected function writable($throw = true)
    {
        $bool = ($this->status & RS_WRITE) === RS_WRITE;
        if (!$bool && $throw) {
            throw new \Exception("Role<{$this->name}> not allow change.");
        }
        return $bool;
    }

    protected function parsePermission($permissions)
    {
        if ($permissions instanceof Permission) {
            $permissions = [$permissions];
        }
        $permissions = collect($permissions);

        $scope = $this->getPermissionScope();
        $items = $permissions->groupBy(function ($item) use ($scope) {
            if (is_numeric($item)) {
                return 'id';
            }
            if (is_string($item)) {
                return 'name';
            }
            if ($item instanceof Permission) {
                if ($scope && !in_array($scope, $item->scopes)) {
                    throw new \Exception('Permission not in role scope');
                }
                return 'model';
            }
            return 'others';
        });

        $result = collect();
        if ($items->has('id')) {
            $result = $result->merge(Permission::findById($items['id'], $scope));
        }
        if ($items->has('name')) {
            $result = $result->merge(Permission::findByName($items['id'], $scope));
        }
        if ($items->has('model')) {
            $result = $result->merge($items['model']);
        }
        if ($result->count() === $permissions->count()) {
            return $result;
        }
        throw new \Exception('Permission not exists');
    }
}
