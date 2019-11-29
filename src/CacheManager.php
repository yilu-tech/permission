<?php


namespace YiluTech\Permission;

class CacheManager
{
    protected $config;

    protected $prefix;

    protected $driver;

    public function __construct(array $config = null)
    {
        $this->config = $config ?: config('permission.cache', []);
        $this->setPrefix($this->config['prefix'] ?? 'permission');
    }

    public function user($user)
    {
        return new UserPermissionCache($user, $this);
    }

    public function expire()
    {
        return ($this->config['expire'] ?? 0) * 86400;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function setPrefix($name)
    {
        $this->prefix = trim($name, ':') . ':';
    }

    public function applyPrefix($key)
    {
        return $this->prefix . $key;
    }

    public function driver()
    {
        if (!$this->driver) {
            $this->driver = \Redis::connection();
        }
        return $this->driver;
    }

    public function empty($role = null)
    {
        $query = \DB::table('user_has_roles')->groupBy('user_id')->select('user_id', \DB::raw('count(1) as count'));
        if ($role) {
            $query->where('role_id', $role->id);
        }
        $query->orderBy('user_id')->each(function ($item) {
            $this->driver()->eval(RedisLuaScript::DEL, 1, $this->applyPrefix($item->user_id . ':keys'));
        });
    }
}
