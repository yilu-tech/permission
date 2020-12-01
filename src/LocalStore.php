<?php


namespace YiluTech\Permission;


class LocalStore
{
    protected $service;

    protected $name = null;

    protected $migrations = [];

    public function __construct($service, $name = null)
    {
        $this->name = $name;
        $this->service = $service;
    }

    public function name()
    {
        return $this->name;
    }

    public function service()
    {
        return $this->service;
    }

    public function addMigration($path)
    {
        $name = basename($path);
        if ($this->check($name)) {
            $this->migrations[$name] = $path;
        }
    }

    public function migrate()
    {
        return array_keys(tap($this->getUndoMigrations(), function ($migrations) {
            if (!empty($migrations)) {
                $batch = new MigrationBatch($migrations);
                $batch->migrate($this->service());
            }
        }));
    }

    public function rollback()
    {
        $migration = new Migration($this->service);
        if (!$migration->lastBatch()) {
            return [];
        }
        return \DB::transaction(function () use ($migration) {
            $manager = new PermissionManager($this->service);
            $manager->rollback();
            return $migration->rollback()->pluck('migration')->all();
        });
    }

    public function items()
    {
        $manager = new PermissionManager($this->service);
        $exceptScopes = ['__' . $this->service];
        return array_values(array_map(function ($item) use ($exceptScopes) {
            $item->addHidden(['id', 'created_at', 'updated_at']);
            $item = $item->toArray();
            $item['scopes'] = array_values(array_diff($item['scopes'], $exceptScopes));
            return array_filter($item);
        }, $manager->items()));
    }

    public function path()
    {
        return base_path($this->config['migration_path'] ?? 'permissions');
    }

    public function check($migration)
    {
        if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}(?:_(.+))?\.(json|yaml)$/', $migration, $matches)) {
            return $this->name == ($matches[1] ?? null);
        }
        return false;
    }

    public function getUndoMigrations()
    {
        $migrated = $this->getMigrated();
        return array_filter($this->migrations, function ($key) use ($migrated) {
            return !in_array($key, $migrated);
        }, ARRAY_FILTER_USE_KEY);
    }

    protected function getMigrated()
    {
        $migration = app(Migration::class, ['service' => $this->service()]);
        return $migration->migrated(-1)->pluck('migration')->all();
    }
}
