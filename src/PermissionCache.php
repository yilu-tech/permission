<?php


namespace YiluTech\Permission;


class PermissionCache
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $user;

    protected $prefix;

    protected $expire;

    public function __construct($user)
    {
        $this->user = $user;
        $this->prefix = config('permission.cache.prefix') ?: 'permission';
        $this->expire = config('permission.cache.expire');
    }

    public function sync()
    {
        $cacheValues = $this->getCacheValue();
        $prefix = $this->getCachePrefix();

        \Redis::multi();
        $this->clear();
        $keys = ["$prefix:keys"];
        foreach ($cacheValues as $key => $value) {
            $key = $key ? "$prefix:$key" : "$prefix:default";
            if (is_array($value)) {
                \Redis::hmset($key, $value);
            } else {
                \Redis::set($key, $value);
            }
            $keys[] = $key;
        }
        \Redis::sadd("$prefix:keys", $keys);
        if ($this->expire) {
            foreach ($keys as $key) \Redis::expire($key, $this->expire * 86400);
        }
        \Redis::exec();
        return $this;
    }

    public function clear()
    {
        $prefix = $this->getCachePrefix();
        $keys = \Redis::smembers("$prefix:keys");
        if (is_array($keys) && count($keys)) \Redis::del($keys);
    }

    public function getCachePrefix()
    {
        return $this->prefix . ':' . $this->user->id;
    }

    protected function getCacheValue()
    {
        $values['sync_time'] = date('Y-m-d H:i:s');
        $values['administrator'] = $this->user->hasAllRoles();
        foreach ($this->user->roles()->groupBy('group') as $group => $roles) {
            foreach ($roles as $role) {
                if ($role->isAdministrator()) {
                    $values[$group . ':is_administrator'] = 1;
                    break;
                }
            }
            if (isset($values[$group . ':is_administrator'])) continue;
            $permissions = $roles->flatMap(function ($role) {
                return $role->permissions();
            });
            foreach ($permissions as $permission) {
                $values[$group][$permission->name] = $permission->config;
            }
        }
        return $values;
    }
}
