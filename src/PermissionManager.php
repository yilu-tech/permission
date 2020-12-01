<?php


namespace YiluTech\Permission;

use YiluTech\Permission\Models\Permission;

class PermissionManager
{
    private $service;

    /**
     * @var Permission[]
     */
    private $items;

    private $changes = [];

    public function __construct(string $service)
    {
        $this->service = $service;
        $this->boot();
    }

    protected function query()
    {
        return Permission::query('__' . $this->service, false);
    }

    protected function log()
    {
        return \DB::table('permission_logs');
    }

    protected function boot()
    {
        $this->items = [];
        $this->query()->each(function ($item) {
            $this->items[$item->name] = $item;
        });
    }

    public function lastVersion()
    {
        return array_reduce($this->items, function ($max, $item) {
            return max($max, $item->version);
        }, 0);
    }

    public function items()
    {
        return $this->items;
    }

    public function getChange($name)
    {
        return $this->changes[$name] ?? [null, null];
    }

    public function create($name, $data, $date)
    {
        if (isset($this->items[$name])) {
            throw new PermissionException(sprintf('permission name "%s" exists', $name));
        }
        array_unshift($data['scopes'], '__' . $this->service);
        $data['updated_at'] = $date;

        [$action, $item] = $this->getChange($name);
        if ($action === 'delete') {
            $this->changes[$name] = ['update', $item->fill($data)];
        } else {
            $data['created_at'] = $date;
            $item = new Permission($data);
            $this->changes[$name] = ['create', $item];
        }

        $this->items[$name] = $item;
    }

    public function update($name, $changes, $date)
    {
        if (empty($this->items[$name])) {
            throw new PermissionException(sprintf('permission name "%s" not found, can not update', $name));
        }
        $item = $this->items[$name];
        $item->fill($this->applyChange($item->toArray(), $changes));

        [$action, $curr] = $this->getChange($name);
        if ($curr) {
            if ($item->name !== $name) {
                $this->changes[$item->name] = [$action, $item];
                unset($this->changes[$name]);
            }
        } else {
            if ($item->isDirty()) {
                $item->updated_at = $date;
                $this->changes[$item->name] = ['update', $item];
            }
        }

        if ($item->name !== $name) {
            $this->items[$item->name] = $item;
            unset($this->items[$name]);
        }
    }

    public function delete($name)
    {
        if (empty($this->items[$name])) {
            throw new PermissionException(sprintf('permission name "%s" not found, can not delete', $name));
        }

        [$action, $item] = $this->getChange($name);
        if ($action === 'create') {
            unset($this->changes[$name]);
        } else {
            $this->changes[$name] = ['delete', $this->items[$name]];
        }
        unset($this->items[$name]);
    }

    public function save($version)
    {
        foreach ($this->changes as [$action, $item]) {
            if ($action !== 'create') {
                $this->log()->insert([
                    'name' => $item->name,
                    'content' => json_encode($item->getOriginal()),
                    'version' => $version,
                    'updated_at' => $item->getOriginal('updated_at')
                ]);
            }
            if ($action === 'delete') {
                $del[] = $item->getKey();
            } else {
                $item->version = $version + 1;
                $item->save();
            }
        }
        if (isset($del)) {
            $this->query()->whereKey($del)->delete();
        }
        $this->changes = [];
    }

    public function rollback()
    {
        $version = $this->lastVersion();

        $current = array_filter($this->items, function ($item) use ($version) {
            return $item->version == $version;
        });

        $originals = $this->log()->where('version', $version - 1)->get();
        foreach ($originals as $original) {
            $content = json_decode($original->content, JSON_OBJECT_AS_ARRAY);
            if (isset($current[$original->name])) {
                $item = $current[$original->name];
                if ($content['name'] !== $original->name) {
                    unset($this->items[$original->name]);
                }
                unset($current[$original->name]);
            } else {
                $item = new Permission();
            }
            $item->setRawAttributes($content)->save();
            $this->items[$item->name] = $item;
        }

        foreach ($current as $name => $item) {
            $del[] = $item->getKey();
            unset($this->items[$name]);
        }

        if (isset($del)) {
            $this->query()->whereKey($del)->delete();
        }

        if (!empty($original)) {
            $this->log()->where('version', $version - 1)->delete();
        }

        return $this;
    }

    protected function applyChange(array $origin, $changes)
    {
        foreach ($changes as $key => $items) {
            if (!isset($origin[$key])) {
                $origin[$key] = null;
            }
            foreach ($items as $change) {
                switch ($change['action']) {
                    case '|<':
                    case '>|':
                        if (isset($change['attr'])) {
                            $value = data_get($origin[$key], $change['attr']);
                            Utils::data_merge($value, $change['value']);
                            data_set($origin[$key], $change['attr'], $value);
                        } else {
                            Utils::data_merge($origin[$key], $change['value']);
                        }
                        break;
                    case '|>':
                    case '<|':
                        if (isset($change['attr'])) {
                            if (is_null($change['value'])) {
                                Utils::data_del($origin[$key], $change['attr']);
                            } else {
                                $value = data_get($origin[$key], $change['attr']);
                                Utils::data_split($value, $change['value']);
                                data_set($origin[$key], $change['attr'], $value);
                            }
                        } else {
                            Utils::data_split($origin[$key], $change['value']);
                        }
                        break;
                    default:
                        if (isset($change['attr'])) {
                            data_set($origin[$key], $change['attr'], $change['value']);
                        } else {
                            $origin[$key] = $change['value'];
                        }
                        break;
                }
            }
        }
        return $origin;
    }
}
