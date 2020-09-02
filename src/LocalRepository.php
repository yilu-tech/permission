<?php

namespace YiluTech\Permission;


use Illuminate\Support\Arr;
use YiluTech\Permission\Models\Permission;

class LocalRepository
{
    /**
     * @var string
     */
    protected $serverScope;

    public function __construct($serverScope)
    {
        $this->serverScope = $serverScope;
    }

    public function sync($items)
    {
        try {
            \DB::beginTransaction();
            $counter = ['create' => 0, 'update' => 0, 'delete' => 0];
            $this->each($items, function ($model, $item) use (&$counter) {
                if (!$model) {
                    Permission::create($item);
                    $counter['create']++;
                } else if (!$item) {
                    $model->delete();
                    $counter['delete']++;
                } else if ($model->fill($item)->getDirty()) {
                    $model->save();
                    $counter['update']++;
                }
            });
            \DB::commit();
            return $counter;
        } catch (\Exception $exception) {
            \DB::rollBack();
            throw $exception;
        }
    }

    public function getChanges($items)
    {
        $this->each($items, function ($model, $item) use (&$changes) {
            if ($model) {
                if ($item) {
                    if ($dirty = $model->fill($item)->getDirty()) {
                        $changes[] = ['action' => 'update', 'name' => $model->name, 'data' => $dirty, 'origin' => Arr::only($model->getOriginal(), array_keys($dirty))];
                    }
                } else {
                    $changes[] = ['action' => 'delete', 'name' => $model->name];
                }
            } else {
                $changes[] = ['action' => 'create', 'data' => $item];
            }
        });
        return $changes ?? [];
    }

    protected function each($items, $callback)
    {
        Permission::query($this->serverScope, false)->each(function ($item) use (&$items, $callback) {
            if (isset($items[$item->name])) {
                $current = $items[$item->name];
                $this->bindServerScope($current);

                $callback($item, $current);
                unset($items[$item->name]);
            } else {
                $callback($item, null);
            }
        });
        foreach ($items as $key => $item) {
            $this->bindServerScope($item);
            $callback(null, $item);
        }
    }

    protected function bindServerScope(&$permission)
    {
        if ($this->serverScope) {
            array_unshift($permission['scopes'], $this->serverScope);
        }
    }
}
