<?php


namespace YiluTech\Permission;

use Illuminate\Support\Arr;
use YiluTech\Permission\Helper\Helper;

class PermissionManager
{
    protected $server;

    protected $logger;

    protected $config;

    protected $stores;

    protected $scopes;

    public function __construct($server = null)
    {
        $this->config = config('permission');

        $this->server = $server ?: $this->config('server');

        $this->logger = new LoggerRepository($this->config('migration_path'));

        $this->initStores();
    }

    public function config($name = null, $default = null)
    {
        return Arr::get($this->config, $name, $default);
    }

    public function all()
    {
        return $this->format(RoutePermission::all());
    }

    public function localStore()
    {
        return $this->store($this->stores['local']);
    }

    public function sync($time = null)
    {
        $counter = 0;
        foreach ($this->stores as $store) {
            $counter += $this->store($store)->sync($time);
        }
        return $counter;
    }

    public function rollback($time = null)
    {
        $counter = 0;
        foreach ($this->stores as $store) {
            $counter += $this->store($store)->rollback($time);
        }
        return $counter;
    }

    public function record()
    {
        $old = array_map(function ($item) {
            if ($item['action'] !== 'CREATE') {
                throw new PermissionException('Permission[:name] recorded error.', ['name' => $item['name']]);
            }
            return $item['data'];
        }, $this->logger->read()[1]);

        $changes = $this->getChanges($old);

        if (!empty($changes)) {
            $this->logger->write($changes);
        }
        return count($changes);
    }

    public function setFilePath($path)
    {
        $this->logger->setPath($path);
        return $this;
    }

    public function getChanges(array $old, $new = null)
    {
        if (!$new) {
            $new = $this->all();
        }
        $differ = function ($a, $b) {
            return $a == $b ? 0 : 1;
        };
        $changes = [];
        foreach (array_udiff_assoc($new, $old, $differ) as $key => $item) {
            if (isset($old[$key])) {
                $changes[$key] = ['action' => 'UPDATE', 'data' => array_udiff_assoc($item, $old[$key], $differ)];
            } else {
                $changes[$key] = ['action' => 'CREATE', 'data' => $item];
            }
        }
        foreach (array_udiff_assoc($old, $new, $differ) as $key => $item) {
            if (empty($new[$key])) {
                $changes[$key] = ['action' => 'DELETE', 'data' => null];
            }
        }
        return $changes;
    }

    public function serverScopeName()
    {
        if ($this->server) {
            return '__' . $this->server;
        }
        return null;
    }

    protected function initStores()
    {
        $this->stores = [];
        if (!empty($remote = $this->config('remote'))) {
            if (is_array($remote)) {
                foreach ($remote as $key => $value) {
                    $scopes = ($key === '*' || is_integer($key)) ? '*' : explode('|', $key);
                    $this->stores[] = ['scopes' => $scopes, 'url' => $value];
                }
            } else {
                $this->stores[] = ['scopes' => '*', 'url' => $remote];
            }
        }
        if ($local = $this->config('local')) {
            $this->stores['local'] = ['scopes' => $local == '*' ? $local : explode('|', $local)];
        } elseif (!$remote) {
            $this->stores['local'] = ['scopes' => '*'];
        }

        $this->scopes = [];
        foreach ($this->stores as $store) {
            if ($store['scopes'] === '*') {
                $this->scopes = '*';
                break;
            } else {
                $this->scopes = array_merge($this->scopes, $store['scopes']);
            }
        }

        if ($this->scopes !== '*') {
            $this->scopes = array_unique($this->scopes);
        }
    }

    protected function filter($item)
    {
        if (!empty($item['content']['rbac_ignore'])) {
            return false;
        }
        if ($this->scopes === '*') {
            return true;
        }
        $intersect = array_uintersect($item['scopes'], $this->scopes, [Helper::class, 'scope_differ']);
        return !empty($intersect);
    }

    protected function store($config)
    {
        $scopes = [$this->serverScopeName(), $config['scopes']];
        if (empty($config['url'])) {
            return new LocalRepository($this->logger, $scopes);
        }
        return new RemoteRepository($this->logger, $scopes, $config['url'], $this->server);
    }

    protected function format(array $items)
    {
        $data = [];
        foreach ($items as $item) {
            switch ($item['type']) {
                case 'api':
                    $item = $this->routeFormatter($item, $data[$item['name']] ?? null);
                    break;
                default:
                    break;
            }

            if ($this->filter($item)) {
                $data[$item['name']] = $item;
            }
        }
        return $data;
    }

    protected function routeFormatter($item, $config = null)
    {
        $data = [
            'name' => $item['name'],
            'type' => $item['type'],
            'scopes' => [],
            'content' => [
                'url' => $item['path'],
                'method' => $item['method']
            ]
        ];

        if ($scope = $this->serverScopeName()) {
            array_unshift($data['scopes'], $scope);
        }

        if (!empty($item['rbac_ignore'])) {
            $data['content']['rbac_ignore'] = $item['rbac_ignore'];
        }

        if ($item['auth'] === '*') {
            $data['scopes'][] = '*';
        } else {
            foreach ($item['auth'] as $auth) {
                $data['scopes'] = array_merge($data['scopes'], $this->parseRouteScope($auth));
            }
        }
        return $config ? $this->mergeRouteConfig($config, $data) : $data;
    }

    protected function mergeRouteConfig($config, $_)
    {
        $config['scopes'] = array_values(array_unique(array_merge($config['scopes'], $_['scopes'])));
        if (empty($config['content']) || Arr::isAssoc($config['content'])) {
            $config['content'] = [$config['content'], $_['content']];
        } else {
            $config['content'][] = $_['content'];
        }
        return $config;
    }

    protected function parseRouteScope($scope)
    {
        $parts = explode('.', $scope, 2);
        if (empty($parts[1])) {
            return [$parts[0]];
        }
        $scopes = [];
        foreach (explode(',', $parts[1]) as $item) {
            $scopes[] = $item && $item !== 'default' ? "{$parts[0]}.$item" : $parts[0];
        }
        return $scopes;
    }
}
