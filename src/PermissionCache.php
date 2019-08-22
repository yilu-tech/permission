<?php


namespace YiluTech\Permission;

use Illuminate\Support\Facades\Redis;

class PermissionCache
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $user;

    protected $prefix;

    protected $expire;

    protected $driver;

    public function __construct($user)
    {
        $this->user = $user;
        $this->prefix = config('permission.cache.prefix') ?: 'permission';
        $this->expire = config('permission.cache.expire');
    }

    protected function getDriver()
    {
        if (!$this->driver) {
            $this->driver = Redis::connection();
            $this->driver->getOptions()->prefix->setPrefix($this->getCachePrefix());
        }
        return $this->driver;
    }

    public function sync(array $values = [])
    {
        $values = array_merge($this->getCacheValue(), $values);
        $redis = $this->getDriver();

        $this->clear();

        $redis->multi();
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $redis->hmset($key, $value);
            } else {
                $redis->set($key, $value);
            }
            $keys[] = $key;
        }
        $keys[] = 'keys';
        $redis->sadd('keys', $keys);
        if ($this->expire) {
            foreach ($keys as $key) $redis->expire($key, $this->expire * 86400);
        }
        $redis->exec();
        return $this;
    }

    public function clear()
    {
        $redis = $this->getDriver();
        $keys = $redis->smembers('keys');
        if (is_array($keys) && count($keys)) $redis->del($keys);
    }

    public function getCachePrefix()
    {
        return $this->prefix . ':' . $this->user->id . ':';
    }

    protected function getCacheValue()
    {
        $values['sync_time'] = date('Y-m-d H:i:s');
        $values['administrator'] = $this->user->hasAllRoles() ? 1 : 0;
        foreach ($this->user->roles()->groupBy('group') as $group => $roles) {
            foreach ($roles as $role) {
                if ($role->isAdministrator()) {
                    $values["$group:is_administrator"] = 1;
                    break;
                }
            }
            if (isset($values["$group:is_administrator"])) continue;
            $permissions = $roles->flatMap(function ($role) {
                return $role->permissions();
            });
            foreach ($permissions as $permission) {
                $values[$group][$permission->name] = $permission->config ?: 'null';
            }
        }
        return $values;
    }
}
