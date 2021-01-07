<?php


namespace YiluTech\Permission;

use YiluTech\Permission\Models\Permission;

class PermissionManager extends PermissionCollection
{
    private $service;

    /**
     * @var Migration
     */
    private $migration;

    public function __construct(string $service)
    {
        $this->service = $service;
        $this->migration = new Migration($service);
        $this->boot();
    }

    protected function query()
    {
        return Permission::query('__' . $this->service, false);
    }

    protected function log()
    {
        return \DB::table('permission_logs')->where('service', $this->service);
    }

    protected function boot()
    {
        $this->items = [];
        $this->query()->each(function ($item) {
            $this->items[$item->name] = $item;
        });
    }

    public function migration()
    {
        return $this->migration;
    }

    public function lastVersion()
    {
        return $this->migration->lastBatch();
    }

    public function save(array $files)
    {
        $version = $this->lastVersion();
        foreach ($this->changes as [$action, $item]) {
            if ($action !== 'create') {
                $this->log()->insert([
                    'name' => $item->name,
                    'service' => $this->service,
                    'content' => json_encode($item->getOriginal()),
                    'version' => $version,
                    'updated_at' => $item->getOriginal('updated_at')
                ]);
            }
            if ($action === 'delete') {
                $del[] = $item->getKey();
            } else {
                $item->scopes = array_values(array_unique(array_merge(['__' . $this->service], $item->scopes)));
                $item->version = $version + 1;
                $item->save();
            }
        }
        if (isset($del)) {
            $this->query()->whereKey($del)->delete();
        }
        $this->changes = [];

        $this->migration->migrate($files);
        return $this;
    }

    public function rollback()
    {
        $version = $this->lastVersion();
        if (!$version) {
            return $this;
        }

        $current = array_filter($this->items, function ($item) use ($version) {
            return $item->version == $version;
        });

        $originals = $this->log()->where('version', $version - 1)->orderByDesc('id')->get();
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

        $this->migration->rollback();
        return $this;
    }

    public function mergeTo(string $file)
    {
        if (!$this->isEmpty()) {
            $this->query()->update(['version' => 1]);
            $this->log()->delete();
            $this->migration->mergeTo($file);
        }
        return $this;
    }
}
