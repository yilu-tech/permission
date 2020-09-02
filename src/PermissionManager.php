<?php


namespace YiluTech\Permission;

use Illuminate\Support\Arr;
use YiluTech\Permission\Helper\Helper;

class PermissionManager
{
    protected $server;

    protected $config;

    protected $stores;

    protected $scopes;

    protected $translations;

    public function __construct($server = null)
    {
        $this->config = config('permission');
        $this->server = $server ?? $this->config('server');
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

    public function getChanges()
    {
        $all = $this->all();
        $changes = [];
        foreach ($this->stores as $store) {
            $items = array_filter($all, function ($item) use ($store) {
                return Helper::scope_exists($item['scopes'], $store['scopes']);
            });
            $changes[] = [
                'server' => $store['url'] ?? 'local',
                'changes' => $this->store($store)->getChanges($items)
            ];
        }
        return $changes;
    }

    public function sync()
    {
        $all = $this->all();
        $counter = [];
        foreach ($this->stores as $store) {
            $items = array_filter($all, function ($item) use ($store) {
                return Helper::scope_exists($item['scopes'], $store['scopes']);
            });
            $counter[] = [
                'server' => $store['url'] ?? 'local',
                'total' => $this->store($store)->sync($items)
            ];
        }
        return $counter;
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
        return Helper::scope_exists($item['scopes'], $this->scopes);
    }

    protected function store($config)
    {
        if (empty($config['url'])) {
            return new LocalRepository($this->serverScopeName());
        }
        return new RemoteRepository($this->server, $config['url']);
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
            if (!$this->filter($item)) {
                continue;
            }
            if (isset($data[$item['name']])) {
                $data[$item['name']] = $this->mergeConfig($data[$item['name']], $item);
            } else {
                $item['translations'] = $this->getTranslations($item['name']);
                $data[$item['name']] = $item;
            }
        }
        return $data;
    }

    protected function getTranslations($name)
    {
        $lang = $this->config('lang', []);
        $translations = [];
        foreach ($lang as $local) {
            $this->loadTranslations($local);
            if ($translation = $this->translations[$local][$name] ?? null) {
                if (is_string($translation)) {
                    $translations[$local] = ['content' => $translation];
                } else {
                    $translations[$local] = $translation;
                }
            }
        }
        return empty($translations) ? null : $translations;
    }

    protected function loadTranslations($local)
    {
        if (isset($this->translations[$local])) {
            return;
        }
        $filename = resource_path("lang/$local/permission.php");
        if (file_exists($filename)) {
            $this->translations[$local] = require $filename;
        } else {
            $this->translations[$local] = [];
        }
    }

    protected function routeFormatter($item)
    {
        $data = [
            'name' => $item['name'],
            'type' => $item['type'],
            'scopes' => [],
            'content' => ['url' => $item['path'], 'method' => $item['method']]
        ];

        if (!empty($item['rbac_ignore'])) {
            $data['content']['rbac_ignore'] = $item['rbac_ignore'];
        }

        if ($item['auth'] === '*') {
            $data['scopes'][] = '*';
        } else {
            foreach ($item['auth'] as $auth) {
                $data['scopes'] = array_merge($data['scopes'], $this->parseRouteScope($auth));
            }
            usort($data['scopes'], [Helper::class, 'scope_cmp']);
        }
        return $data;
    }

    protected function mergeConfig($config, $other)
    {
        if ($config['scopes'] != $other['scopes']) {
            $config['scopes'] = array_unique(array_merge($config['scopes'], $other['scopes']));
            sort($config['scopes']);
        }
        if (empty($config['content'][0])) {
            $config['content'] = [$config['content']];
        }
        foreach ($config['content'] as $index => $value) {
            if ($value['url'] < $other['content']['url']) {
                array_splice($config['content'], $index, 0, [$other['content']]);
                return $config;
            }
        }
        $config['content'][] = $other['content'];
        return $config;
    }

    protected function mergeConfigContent($config, $item)
    {
        if (empty($config['content'][0])) {
            $config['content'] = [$config['content']];
        }
        foreach ($config['content'] as $index => $value) {
            if ($value['url'] > $item['content']['url']) {
                array_splice($config['content'], $index, 0, [$item['content']]);
                return $config;
            }
        }
        $config['content'][] = $item['content'];
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
