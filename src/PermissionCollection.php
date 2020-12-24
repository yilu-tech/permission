<?php


namespace YiluTech\Permission;

use YiluTech\Permission\Models\Permission;

class PermissionCollection
{
    protected $items = [];

    protected $changes = [];

    public function match($name): array
    {
        if (strpos($name, '*') === false) {
            if (isset($this->items[$name])) {
                return [$this->items[$name]];
            }
            return [];
        }
        $pattern = '/^' . str_replace(['.', '**', '*', '%'], ['\.', '.%', '[\w\-]+', '*'], $name) . '$/';
        return array_filter($this->items, function ($name) use ($pattern) {
            return preg_match($pattern, $name);
        }, ARRAY_FILTER_USE_KEY);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function items(): array
    {
        return $this->items;
    }

    public function getChange($name): array
    {
        return $this->changes[$name] ?? [null, null];
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function create($name, $data, $date): self
    {
        if (isset($this->items[$name])) {
            throw new PermissionException(sprintf('permission name "%s" exists', $name));
        }
        $data['updated_at'] = $date;

        [$action, $item] = $this->getChange($name);
        if ($action === 'delete') {
            unset($this->changes[$name]);
            $this->changes[$name] = ['update', $item->fill($data)];
        } else {
            $data['created_at'] = $date;
            $item = new Permission($data);
            $this->changes[$name] = ['create', $item];
        }
        $this->items[$name] = $item;
        return $this;
    }

    public function update($name, $changes, $date): self
    {
        if (empty($matches = $this->match($name))) {
            throw new PermissionException(sprintf('permission name "%s" not match, nothing to update', $name));
        }
        foreach ($matches as $item) {
            $name = $item->getAttribute('name');
            $item->fill($this->applyChange($item->toArray(), $changes));
            [$action, $curr] = $this->getChange($name);
            if ($curr) {
                unset($this->changes[$name]);
                $this->changes[$item->name] = [$action, $item];
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
        return $this;
    }

    public function delete($name): self
    {
        if (empty($matches = $this->match($name))) {
            throw new PermissionException(sprintf('permission name "%s" not match, nothing to delete', $name));
        }
        foreach ($matches as $item) {
            $name = $item->getAttribute('name');
            [$action, $item] = $this->getChange($name);
            unset($this->changes[$name]);
            if ($action !== 'create') {
                $this->changes[$name] = ['delete', $this->items[$name]];
            }
            unset($this->items[$name]);
        }
        return $this;
    }

    protected function applyChange(array $origin, $changes): array
    {
        foreach ($changes as $key => $items) {
            if (!is_array($items)) {
                $origin[$key] = $items;
                continue;
            }
            if (!isset($origin[$key])) {
                $origin[$key] = null;
            }
            foreach ($items as $change) {
                switch ($change['action']) {
                    case '|<':
                    case '>|':
                        if (isset($change['attr'])) {
                            $value = data_get($origin[$key], $change['attr']);
                            Utils::data_merge($value, $change['value'], $change['action'] === '|<');
                            data_set($origin[$key], $change['attr'], $value);
                        } else {
                            Utils::data_merge($origin[$key], $change['value'], $change['action'] === '|<');
                        }
                        break;
                    case '|>':
                    case '<|':
                        if (isset($change['attr'])) {
                            if (is_null($change['value'])) {
                                Utils::data_del($origin[$key], $change['attr']);
                            } else {
                                $value = data_get($origin[$key], $change['attr']);
                                Utils::data_split($value, $change['value'], $change['action'] === '|>');
                                data_set($origin[$key], $change['attr'], $value);
                            }
                        } else {
                            Utils::data_split($origin[$key], $change['value'], $change['action'] === '|>');
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
