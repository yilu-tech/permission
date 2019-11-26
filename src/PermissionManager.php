<?php


namespace YiluTech\Permission;


use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use YiluTech\Permission\Models\Permission;

class PermissionManager
{
    public $filter;

    protected $items;

    protected $server;

    protected $filePath;

    public function __construct($server = null)
    {
        $this->server = $server ?: config('permission.server');
        $this->setFilePath(config('permission.migration_path'));
    }

    public function old()
    {
        return $this->readFile(null, null, true);
    }

    public function all()
    {
        if (!$this->items) {
            $this->items = $this->format(RoutePermission::all());
        }
        if (!$this->filter) {
            return $this->items;
        }
        return array_filter($this->items, $this->filter);
    }

    public function getLastUpdateTime()
    {
        return $this->isClient()
            ? Permission::query($this->serverScopeName())->max('updated_at')
            : $this->callRemote('getLastUpdateTime');
    }

    public function saveChanges()
    {
        $changes = $this->readFile($this->getLastUpdateTime());
        $this->writeDB($changes);
    }

    public function writeDB(array $changes)
    {
        if (empty($changes)) return;

        if (!$this->isClient()) {
            $this->callRemote('update', $changes);
            return;
        }

        \DB::transaction(function () use ($changes) {
            foreach ($changes as $name => $change) {

                if (!empty($change['data'])) {
                    $change['data'] = array_map(function ($data) {
                        return is_array($data) ? json_encode($data) : $data;
                    }, $change['data']);

                    $change['data']['updated_at'] = $change['date'];
                }

                switch ($change['action']) {
                    case 'CREATE':
                        $change['data']['created_at'] = $change['date'];
                        \DB::table('permissions')->insert($change['data']);
                        break;
                    case 'UPDATE':
                        \DB::table('permissions')->where('name', $name)->update($change['data']);
                        break;
                    default:
                        \DB::table('permissions')->where('name', $name)->delete();
                        break;
                }
            }
        });
    }

    public function rollbackChanges($date)
    {
        $data = $this->readFile(null, $date, true);
        $changes = array_map(function ($change) use ($data) {
            switch ($change['action']) {
                case 'CREATE':
                    return ['action' => 'DELETE'];
                case 'UPDATE':
                    $change['data'] = $data[$change['name']];
                    return $change;
                default:
                    $change['action'] = 'CREATE';
                    $change['data'] = $data[$change['name']];
                    return $change;
            }
        }, $this->readFile($date));
        $this->writeDB($changes);
    }

    public function readFile($start = null, $end = null, $only_data = false)
    {
        $file = $this->getFilePath('permission.log');
        $data = [];
        if (file_exists($file)) {
            $fs = fopen($file, 'r');
            while (!feof($fs)) {
                $row = trim(fgets($fs));
                if (!$row) continue;

                $item = $this->parseLine($row);
                if ($start && $item['date'] <= $start) continue;
                if ($end && $item['date'] > $end) break;
                $only_data ? $this->mergeData($data, $item) : $this->merge($data, $item);
            }
            fclose($fs);
        }
        return $data;
    }

    public function writeFile(array $changes = null)
    {
        if (!$changes) {
            $changes = $this->getChanges($this->old());
        }
        if (empty($changes)) {
            return;
        }
        $date = date('Y-m-d H:i:s');
        $fs = fopen($this->getFilePath('permission.log'), 'a');
        foreach ($changes as $key => $item) {
            fwrite($fs, $this->formatLine($date, $item['action'], $key, $item['data']));
        }
        fclose($fs);
    }

    public function setFilePath($path)
    {
        $this->filePath = trim($path, '/');
        return $this;
    }

    public function getFilePath($filename = null)
    {
        return base_path($this->filePath . '/' . $filename);
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

    public function isClient()
    {
        return !config('permission.remote');
    }

    protected function formatLine($date, $action, $name, $data = null)
    {
        $str = "[$date] $action: $name";
        if ($data) {
            $str .= ' > ' . json_encode($data);
        }
        return $str . PHP_EOL;
    }

    protected function parseLine($str)
    {
        $item['date'] = substr($str, 1, 19);

        $parts = explode(':', substr($str, 21), 2);
        $item['action'] = trim($parts[0]);

        $parts = explode('>', $parts[1], 2);
        $item['name'] = trim($parts[0]);

        if (!empty($parts[1])) {
            $item['data'] = json_decode($parts[1], JSON_OBJECT_AS_ARRAY);
        }
        return $item;
    }

    protected function mergeData(&$items, $item)
    {
        if (isset($items[$item['name']])) {
            if ($item['action'] === 'DELETE') {
                unset($items[$item['name']]);
            } else {
                $items[$item['name']] = array_merge($items[$item['name']], $item['data']);
            }
        } elseif ($item['action'] !== 'DELETE') {
            $items[$item['name']] = $item['data'];
        }
    }

    protected function merge(&$items, $item)
    {
        if (isset($items[$item['name']])) {
            $origin = &$items[$item['name']];
            switch ($item['action']) {
                case 'CREATE':
                    $origin = $item;
                    $origin['action'] = 'UPDATE';
                    break;
                case 'UPDATE':
                    $origin['date'] = $item['date'];
                    $origin['data'] = array_merge($origin['data'], $item['data']);
                    break;
                default:
                    if ($origin['action'] === 'CREATE') {
                        unset($items[$item['name']]);
                    } else {
                        $origin = $item;
                    }
                    break;
            }
        } else {
            $items[$item['name']] = $item;
        }
    }

    protected function callRemote($action, $changes = null)
    {
        $url = config('permission.remote') . '/permission/sync';

        $params = compact('action', 'changes');
        $params['server'] = $this->server;

        return (new Client)->post($url, [
            'json' => array_filter($params),
        ])->getBody()->getContents();
    }

    protected function serverScopeName()
    {
        if ($this->server) {
            return '__' . $this->server;
        }
        return null;
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
            $data[$item['name']] = $item;
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
        if (!empty($change['rbac_ignore'])) {
            $data['content']['rbac_ignore'] = $change['rbac_ignore'];
        }

        if ($item['auth'] === '*') {
            $data['scopes'] = ['*'];
        } else {
            foreach ($item['auth'] as $auth) {
                $parts = explode('.', $auth, 2);
                if (isset($parts[1])) {
                    foreach (explode(',', $parts[1]) as $group) {
                        $data['scopes'][] = $group ? "{$parts[0]}.$group" : $parts[0];
                    }
                } else {
                    $data['scopes'][] = $parts[0];
                }
            }
        }

        if ($scope = $this->serverScopeName()) {
            array_unshift($data['scopes'], $scope);
        }

        if ($config) {
            $data['scopes'] = array_values(array_unique(array_merge($data['scopes'], $config['scopes'])));
            if (empty($config['content']) || Arr::isAssoc($config['content'])) {
                $data['content'] = [$config['content'], $data['content']];
            } else {
                array_push($config['content'], $data['content']);
                $data['content'] = $config['content'];
            }
        }

        return $data;
    }
}
