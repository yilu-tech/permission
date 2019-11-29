<?php


namespace YiluTech\Permission;

use YiluTech\Permission\Traits\HasRoles;

class UserPermissionCache
{
    protected $user;

    protected $manager;

    /**
     * UserPermissionCache constructor.
     * @param HasRoles | \Illuminate\Database\Eloquent\Model $user
     * @param CacheManager|null $manager
     */
    public function __construct($user, $manager = null)
    {
        $this->user = $user;
        $this->manager = $manager ?? resolve(CacheManager::class);
    }

    public function sync(array $values = [])
    {
        $values = array_merge($this->getCacheValue(), $values);
        $this->clear();
        if (empty($values)) {
            return $this;
        }

        $driver = $this->manager->driver();
        $keys = [$this->applyPrefix('keys')];

        $driver->multi();
        foreach ($values as $key => $value) {
            $keys[] = $key = $this->applyPrefix($key);
            if (is_array($value)) {
                $driver->hmset($key, $value);
            } else {
                $driver->set($key, $value);
            }
        }
        $driver->sadd($keys[0], $keys);

        if ($expire = $this->manager->expire()) {
            foreach ($keys as $key) $driver->expire($key, $expire);
        }

        $driver->exec();

        return $this;
    }

    public function clear()
    {
        $this->manager->driver()->eval(RedisLuaScript::DEL, 1, $this->applyPrefix('keys'));
    }

    protected function applyPrefix($key)
    {
        return $this->manager->applyPrefix($this->user->id . ':' . $key);
    }

    protected function getCacheValue()
    {
        $values = [];
        if ($this->user->hasAllRoles()) {
            $values['administrator'] = 1;
        }
        foreach ($this->user->roles()->groupBy('group') as $group => $roles) {
            foreach ($roles as $role) {
                if ($role->isAdministrator()) {
                    $values["$group:administrator"] = 1;
                    break;
                }
            }
            if (isset($values["$group:administrator"])) continue;
            $permissions = $roles->flatMap(function ($role) {
                return $role->permissions();
            });
            foreach ($permissions as $permission) {
                $values[$group][$permission->name] = 1;
            }
        }
        return $values;
    }
}
