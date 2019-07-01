<?php

namespace YiluTech\Permission\Traits;

use YiluTech\Permission\Identity;
use YiluTech\Permission\Util;

trait HasIdentity
{
    public $useIdentity = true;

    public function identityPermissions()
    {
        return Util::array_get($this->relations, 'identityPermissions', function () {
            $useIdentity = $this->useIdentity;
            $this->useIdentity = false;

            $permissions = $this->unsetRelation('roles')->roles()->groupBy(function ($role) {
                return Identity::getCacheKey($role);
            })->map(function ($roles) {
                return $roles->flatMap(function ($role) {
                    return $role->permissions();
                });
            });

            $this->unsetRelation('roles');
            $this->useIdentity = $useIdentity;
            return $permissions;
        });
    }

    public function whereIdentity($query)
    {
        return Identity::whereIdentity($query, $this->getIdentity());
    }

    public function getIdentity()
    {
        $identity = method_exists($this, 'identity')
            ? $this->identity()
            : array_intersect_key($this->original, array_flip(config('permission.identity.names', [])));
        return Identity::formatIdentity($identity);
    }
}
