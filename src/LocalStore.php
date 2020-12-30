<?php


namespace YiluTech\Permission;


class LocalStore
{
    protected $service;

    protected $name = null;

    protected $migrations = [];

    protected $options = [];

    public function __construct($service, $options = [])
    {
        if (is_string($options)) {
            $options = ['name' => $options];
        }
        $this->options = (array)$options;

        $this->name = $this->option('name');
        $this->service = $service;
    }

    public function option($name = null, $default = null)
    {
        if ($name) {
            return $this->options[$name] ?? $default;
        }
        return $this->options;
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

    public function getChanges()
    {
        $migrations = $this->getUndoMigrations();
        $batch = new MigrationBatch($migrations);
        return [array_keys($migrations), $batch->getChanges($this->service)];
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

    public function rollback($steps = 1)
    {
        $manager = new PermissionManager($this->service);
        if (!$manager->lastVersion()) {
            return [];
        }
        return \DB::transaction(function () use ($manager, $steps) {
            $migrations = [];
            for ($i = 0; $i < $steps; $i++) {
                if (!$manager->lastVersion()) {
                    break;
                }
                $migrations = array_merge($migrations, $manager->migration()->migrated()->all());
                $manager->rollback();
            }
            return $migrations;
        });
    }

    public function mergeTo($file)
    {
        $manager = new PermissionManager($this->service);
        if ($manager->isEmpty()) {
            return [[], []];
        }
        return \DB::transaction(function () use ($manager, $file) {
            return [
                $manager->migration()->migrated(-1)->all(),
                $this->formatItems($manager->mergeTo($file)->items())
            ];
        });
    }

    public function items()
    {
        $manager = new PermissionManager($this->service);
        return $this->formatItems($manager->items());
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

    public function getMigrations()
    {
        return $this->migrations;
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
        return $migration->migrated(-1)->all();
    }

    protected function formatItems($items)
    {
        $exceptScopes = ['__' . $this->service];
        return array_map(function ($item) use ($exceptScopes) {
            $item->addHidden(['id', 'created_at', 'updated_at']);
            $item = $item->toArray();
            $item['scopes'] = array_values(array_diff($item['scopes'], $exceptScopes));
            return $item;
        }, $items);
    }
}
