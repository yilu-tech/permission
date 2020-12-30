<?php


namespace YiluTech\Permission;


class StoreManager
{
    protected $config;

    /**
     * @var LocalStore[]
     */
    protected $stores = [];

    protected $tags = [];

    public function __construct($config)
    {
        $this->config = $config;
        $this->boot();
    }

    protected function boot()
    {
        $service = $this->service();
        if (!empty($this->config['endpoints'])) {
            if (array_key_exists('local', $this->config)) {
                $this->stores[] = new LocalStore($service, $this->config['local']);
            }
            if (is_array($this->config['endpoints'])) {
                foreach ($this->config['endpoints'] as $key => $endpoint) {
                    $options = is_array($endpoint) ? $endpoint : ['url' => $endpoint];
                    $options['name'] = is_int($key) ? null : $key;
                    $this->stores[] = new RemoteStore($service, $options);
                }
            } else {
                $this->stores[] = new RemoteStore($service, ['url' => $this->config['endpoints']]);
            }
        } else {
            $this->stores[] = new LocalStore($service, $this->config['local'] ?? null);
        }

        $this->tags = array_unique(array_map(function ($store) {
            return $store->name();
        }, $this->stores));
    }

    public function loadMigrations()
    {
        $directory = base_path($this->config['migration_path'] ?? 'permissions');
        if (!is_dir($directory)) {
            throw new PermissionException(sprintf('Invalid directory %s', $directory));
        }

        $fd = opendir($directory);
        while ($name = readdir($fd)) {
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($path)) {
                $this->addMigration($path);
            }
        }
        closedir($fd);
        return $this;
    }

    public function tags()
    {
        return $this->tags;
    }

    public function stores($name = false)
    {
        return $name === false ? $this->stores : array_filter($this->stores, function ($store) use ($name) {
            return $store->name() == $name;
        });
    }

    protected function addMigration($path)
    {
        foreach ($this->stores as $store) {
            $store->addMigration($path);
        }
    }

    public function service()
    {
        return $this->config['server'];
    }

    public function migrate()
    {
        foreach ($this->stores as $store) {
            $store->migrate();
        }
    }
}
