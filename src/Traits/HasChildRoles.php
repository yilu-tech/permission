<?php

namespace YiluTech\Permission\Traits;

use YiluTech\Permission\Helper\Helper;
use YiluTech\Permission\Models\Role;

trait HasChildRoles
{
    protected $MAX_LEVEL = 3;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function childRoleRelation()
    {
        return $this->belongsToMany(Role::class, 'role_has_roles', 'role_id', 'child_id');
    }

    /**
     * @param $depth
     * @return \Illuminate\Support\Collection
     */
    public function childRoles($depth = 0)
    {
        $roles = Helper::array_get($this->relations, 'childRoles', function () {
            if (!$this->hasChild()) {
                return collect();
            }
            $items = $this->childRoleRelation()->get();
            if ($this->relationLoaded('pivot')) {
                foreach ($items as $item) {
                    $item->pivot->group = $this->pivot->group;
                }
            }
            return $items;
        });
        if ($depth == 0) {
            return $roles;
        }
        return $roles->merge($roles->flatMap(function ($role) use ($depth) {
            return $role->childRoles($depth - 1);
        }));
    }

    public function hasChildRole($id): bool
    {
        return $this->childRoles(INF)->contains('id', $id);
    }

    public function hasAnyChildRoles(array $ids): bool
    {
        return !empty(array_intersect($ids, $this->childRoles(INF)->pluck('id')->all()));
    }

    public function hasAllChildRoles(array $ids): bool
    {
        return empty(array_diff($ids, $this->childRoles(INF)->pluck('id')->all()));
    }

    public function giveChildRoleTo($roles)
    {
        $roles = $this->parseRole($roles);

        $attach = $roles->diffUsing($this->childRoles(), function ($a, $b) {
            return $a->id - $b->id;
        });

        if ($attach->isEmpty()) {
            return 0;
        }

        $relation = $this->childRoleRelation();
        foreach ($attach as $role) {
            if ($role->id === $this->id) {
                throw new \Exception('can not extend self');
            }
            if ($role->isAdministrator()) {
                throw new \Exception('can not extend administrator');
            }
            if (!($role->status & RS_EXTEND)) {
                throw new \Exception("can not extend role<{$role->name}>");
            }
            if ($this->hasChildRole($role->id)) {
                throw new \Exception("role<{$role->name}> already exists");
            }
            if ($role->hasChildRole($role->id)) {
                throw new \Exception('can not extend parent');
            }
            if ($role->getLevel() >= $this->MAX_LEVEL) {
                throw new \Exception("can not extend role<{$role->name}> more than {$this->MAX_LEVEL} level");
            }
            $relation->attach($role->id);

            $role->setRelation('pivot', $relation->newExistingPivot());
            $this->relations['childRoles']->push($role);
        }
        $this->status = $this->status | RS_EXTENDED;
        $this->unsetRelation('permissions');
        return $attach->count();
    }

    public function syncChildRoles($roles)
    {
        $roles = $this->parseRole($roles);

        if ($roles->isEmpty()) {
            return $this->revokeChildRoleTo();
        }

        $current = $this->childRoles()->pluck('id')->all();

        $detached = array_diff($current, $roles->pluck('id')->all());
        if (count($detached)) {
            $result = $this->revokeChildRoleTo($detached);
        } else {
            $result = 0;
        }
        return $result + $this->giveChildRoleTo($roles);
    }

    public function revokeChildRoleTo(array $roles = null)
    {
        $result = $this->childRoleRelation()->detach($roles);
        if ($result) {
            if ($this->relationLoaded('childRoles') && $roles) {
                $roles = $this->childRoles()->filter(function ($item) use ($roles) {
                    if (is_array($roles)) {
                        return !in_array($item->id, $roles);
                    }
                    return $item->id != $roles;
                })->values();
                $this->setRelation('childRoles', $roles);
            }
            if ($roles == null) {
                $this->setRelation('childRoles', collect());
            }
        }
        if ($this->childRoles()->isEmpty()) {
            $this->status = $this->status & ~RS_EXTENDED;
        }
        $this->unsetRelation('permissions');
        return $result;
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
        return $this->getOriginal('status') & RS_EXTENDED;
    }

    protected function parseRole($role)
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
        $group = $this->getAttributeFromArray('group');
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
        throw new \Exception('role not exists');
    }
}
