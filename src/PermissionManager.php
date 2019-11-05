<?php


namespace YiluTech\Permission;


use Illuminate\Support\Arr;

class PermissionManager
{
    protected $items;

    public function all()
    {
        if (!$this->items) {
            $this->items = $this->format(RoutePermission::all());
        }
        return $this->items;
    }

    public function getStoredChanges($path = null)
    {
        $path = $this->getChangesFilePath($path);
        return file_exists($path) ? require $path : [];
    }

    public function isSyncedChanges(array $changes)
    {
        return current($changes) === 'changed';
    }

    public function getStored($path = null)
    {
        $path = $this->getStoredFilePath($path);
        return file_exists($path) ? require $path : [];
    }

    public function getLastStored($path = null)
    {
        $items = $this->getStored($path);
        $changes = $this->getStoredChanges($path);

        if ($this->isSyncedChanges($changes)) {
            array_shift($changes);
        }

        foreach ($changes as $key => $value) {
            if ($value['action'] === 'create') {
                unset($items[$key]);
            } else {
                if ($value['action'] === 'update') {
                    $value = array_merge($value, $value['changes']);
                    unset($value['changes']);
                }
                unset($value['action']);
                $items[$key] = $value;
            }
        }
        return $items;
    }

    public function getChangesFilePath($path = null)
    {
        return $this->getStorePath('changes.php', $path);
    }

    public function getStoredFilePath($path = null)
    {
        return $this->getStorePath('permissions.php', $path);
    }

    public function getStorePath($filename, $path = null)
    {
        return base_path(($path ?: config('permission.migration_path')) . '/' . $filename);
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
                $item['action'] = 'update';
                $item['changes'] = array_udiff_assoc($old[$key], $item, $differ);
            } else {
                $item['action'] = 'create';
            }
            $changes[$key] = $item;
        }
        foreach (array_udiff_assoc($old, $new, $differ) as $key => $item) {
            if (empty($new[$key])) {
                $item['action'] = 'delete';
                $changes[$key] = $item;
            }
        }
        return $changes;
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

        if ($config) {
            $data['scopes'] = array_values(array_unique(array_merge($data['scopes'], $config['scopes'])));
            if (Arr::isAssoc($config['content'])) {
                $data['content'] = [$config['content'], $data['content']];
            } else {
                array_push($config['content'], $data['content']);
                $data['content'] = $config['content'];
            }
        }

        return $data;
    }
}
