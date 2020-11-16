<?php


namespace YiluTech\Permission;

use YiluTech\Permission\Traits\HasRoles;

class UserCache
{
    /**
     * @var \Illuminate\Database\Eloquent\Model|HasRoles
     */
    protected $user;

    /**
     * @var RedisStore
     */
    protected $redis;

    /**
     * UserPermissionCache constructor.
     * @param HasRoles | \Illuminate\Database\Eloquent\Model $user
     * @param RedisStore|null $redis
     */
    public function __construct($user, $redis = null)
    {
        $this->user = $user;
        $this->redis = $redis ?? resolve(RedisStore::class);
    }

    public function sync(array $values = [])
    {
        $values = array_merge($this->getCacheValue(), $values);
        $this->clear();

        if (empty($values)) {
            return $this;
        }

        $driver = $this->redis->driver();

        $driver->pipeline(function ($pipe) use ($values) {
            $keys = [$this->applyPrefix('keys')];
            $pipe->multi();
            foreach ($values as $key => $value) {
                $keys[] = $key = $this->applyPrefix($key);
                if (is_array($value)) {
                    $pipe->hmset($key, $value);
                } else {
                    $pipe->set($key, $value);
                }
            }
            $pipe->sadd($keys[0], $keys);
            if ($expires = $this->redis->getExpires()) {
                foreach ($keys as $key) $pipe->expire($key, $expires);
            }
            $pipe->exec();
        });

        return $this;
    }

    public function clear()
    {
        $driver = $this->redis->driver();
        $keys = $driver->smembers($this->applyPrefix('keys'));
        if (!empty($keys)) {
            $driver->del($keys);
        }
    }

    protected function applyPrefix($key): string
    {
        return $this->redis->applyPrefix($this->user->id, $key);
    }

    protected function getCacheValue(): array
    {
        if ($this->user->hasAllRoles()) {
            return ['administrator' => 1];
        }

        $values = [];
        foreach ($this->user->roles()->groupBy('pivot.group') as $group => $roles) {
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
