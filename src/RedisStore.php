<?php


namespace YiluTech\Permission;

use Illuminate\Support\Facades\Redis;

class RedisStore
{
    protected $config;

    protected $prefix;

    protected $expires;

    protected $driver;

    public function __construct(array $config = null)
    {
        $this->config = $config ?: config('permission.cache', []);
        $this->setPrefix($this->config['prefix'] ?? 'permission');
    }

    public function user($user)
    {
        return new UserCache($user, $this);
    }

    public function setExpires($expires)
    {
        $this->expires = max($expires, 120);
    }

    public function getExpires()
    {
        return $this->expires;
    }

    public function setPrefix($name)
    {
        $this->prefix = trim($name, ':') . ':';
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function applyPrefix($id, $key)
    {
        return '{' . $this->prefix . $id . '}:' . $key;
    }

    public function driver()
    {
        if (!$this->driver) {
            $this->driver = Redis::connection($this->config['connection'] ?? 'default');
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
            $keys = $this->driver()->smembers($this->applyPrefix($item->user_id, 'keys'));
            if (!empty($keys)) {
                $this->driver()->del($keys);
            }
        });
    }
}
