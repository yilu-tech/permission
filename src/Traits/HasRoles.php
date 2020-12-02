<?php

namespace YiluTech\Permission\Traits;

use Illuminate\Support\Facades\Auth;
use YiluTech\Permission\Helper\Helper;
use YiluTech\Permission\Helper\RoleGroup;
use YiluTech\Permission\PermissionException;
use YiluTech\Permission\UserCache;
use YiluTech\Permission\Models\Role;

trait HasRoles
{
    public static function roleChanged($callback)
    {
        static::registerModelEvent('roleChanged', $callback);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roleRelation()
    {
        return $this->belongsToMany(Role::class, 'user_has_roles', 'user_id', 'role_id');
    }

    /**
     * @param  $group
     * @param  $depth
     * @return \Illuminate\Support\Collection
     */
    public function roles($group = false, $depth = 0)
    {
        $roles = Helper::array_get($this->relations, 'roles', function () {
            return $this->roleRelation()->withPivot('group')->get();
        });

        if ($group !== false) {
            $roles = $roles->filter(function ($role) use ($group) {
                return $role->pivot->group == $group;
            })->values();
        }

        if ($depth > 0) {
            $roles = $this->mergeChildRoles($roles, $depth);
        }

        return $roles;
    }

    /**
     * @param $group
     * @return \Illuminate\Support\Collection
     */
    public function permissions($group = false)
    {
        return $this->roles($group)->flatMap(function ($role) {
            return $role->permissions();
        })->unique('id')->values();
    }

    /**
     * @return UserCache
     */
    public function permissionCache()
    {
        return new UserCache($this);
    }

    public function checkAuthorizer()
    {
        return $this->id == Auth::id();
    }

    public function giveRoleTo($roles, $group = false, $basic = true, $fireEvent = true)
    {
        $this->validateAuthorizer();

        $roles = $this->parseRole($roles, $group);
        if ($basic) {
            $roles = $roles->merge(Role::status(RS_BASIC, $group)->get()->all())->unique('id');
        }

        $roles->each(function ($role) {
            $role->group = $this->makeRoleGroup($role);
        });

        $attach = $roles->diffUsing($this->roles(), function ($a, $b) {
            if ($a->id == $b->id) {
                return $a->group == $b->pivot->group ? 0 : -1;
            }
            return $a->id - $b->id;
        });

        if ($attach->isEmpty()) {
            return 0;
        }

        $relation = $this->roleRelation();
        foreach ($attach as $item) {
            $attributes = ['group' => $item->group];
            $relation->attach($item->id, $attributes);

            $item->setRelation('pivot', $relation->newExistingPivot($attributes));
            $this->relations['roles']->push($item);
        }

        if ($fireEvent) {
            $this->fireModelEvent('roleChanged');
            $this->permissionCache()->clear();
        }
        return $attach->count();
    }

    public function syncRoles(array $roles, $group = false)
    {
        $this->validateAuthorizer();

        $roles = Role::status(RS_BASIC, $group)->get()->merge($this->parseRole($roles, $group))->unique('id');
        if ($roles->isEmpty()) {
            return $this->revokeRoleTo(null, $group);
        }

        $current = $this->roles($group)->pluck('id')->all();
        $detached = array_diff($current, $roles->pluck('id')->all());

        if (count($detached)) {
            $result = $this->revokeRoleTo($detached, $group, false);
        } else {
            $result = 0;
        }
        return $result + $this->giveRoleTo($roles->all(), $group, false);
    }

    public function revokeRoleTo($roles = null, $group = false, $fireEvent = true)
    {
        if ($this->checkAuthorizer()) {
            throw new PermissionException('Can not revoke self roles.');
        }

        $relation = $this->roleRelation();

        if ($group !== false) {
            $relation->wherePivot('group', $group ?: '');
        }

        if ($result = $relation->detach($roles)) {

            if ($this->relationLoaded('roles')) {
                $roles = $this->roles()->filter(function ($item) use ($roles, $group) {
                    if ($group !== false && $item->pivot->group != $group) {
                        return true;
                    }
                    if (!$roles) {
                        return false;
                    }
                    if (is_array($roles)) {
                        return !in_array($item->id, $roles);
                    }
                    return $item->id != $roles;
                })->values();
                $this->setRelation('roles', $roles);
            }

            if ($fireEvent) {
                $this->permissionCache()->clear();
                $this->fireModelEvent('roleChanged');
            }
        }
        return $result;
    }

    public function hasRole($id, $group = false): bool
    {
        return $this->roles($group, INF)->contains('id', $id);
    }

    public function hasAnyRoles(array $ids, $group = false): bool
    {
        return !empty(array_intersect($ids, $this->roles($group, INF)->pluck('id')->all()));
    }

    public function hasAllRoles(array $ids = null, $group = false)
    {
        if (!$ids) {
            return method_exists($this, 'isAdministrator') && $this->isAdministrator();
        }
        return empty(array_diff($ids, $this->roles($group, INF)->pluck('id')->all()));
    }

    public function hasRoleGroup($group)
    {
        if ($this->hasAllRoles()) {
            return Role::group($group)->exists();
        }
        foreach ($this->roles() as $role) {
            if ($role->pivot->group == $group) {
                return true;
            }
        }
        return false;
    }

    protected function mergeChildRoles($roles, $depth = INF)
    {
        return $roles->merge($roles->flatMap(function ($role) use ($depth) {
            return $role->childRoles($depth)
                ->each(function ($child) use ($role) {
                    $child->pivot = $role->pivot;
                });
        }));
    }

    protected function validateAuthorizer()
    {
        if ($this->checkAuthorizer()) {
            throw new PermissionException('Can not give role to self.');
        }
    }

    protected function parseRole($role, $group = false)
    {
        if ($role instanceof Role) {
            $role = [$role];
        }

        $roles = collect($role);
        $items = $roles->groupBy(function ($item) {
            if (is_numeric($item)) {
                return 'id';
            }
            if (is_string($item)) {
                return 'name';
            }
            if ($item instanceof Role) {
                return 'model';
            }
            return 'others';
        });

        $result = collect();
        if ($items->has('id')) {
            $result = $result->merge(Role::findById($items['id'], $group));
        }
        if ($items->has('name')) {
            $result = $result->merge(Role::findByName($items['name'], $group));
        }
        if ($items->has('model')) {
            $result = $result->merge($items['model']);
        }
        if ($result->count() === $roles->count()) {
            return $result;
        }
        throw new PermissionException('Role not exists.');
    }

    protected function makeRoleGroup($role)
    {
        $group = RoleGroup::parse($role->group);
        if (empty($group['key'])) {
            return '';
        }
        if (empty($group['value'])) {
            return RoleGroup::make($group['key']);
        }
        return $role->group;
    }
}
