<?php


namespace YiluTech\Permission;


class StoreManager
{
    protected $config;

    /**
     * @var LocalStore[]
     */
    protected $stores = [];

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
                    if (is_int($key)) {
                        $key = null;
                    }
                    $this->stores[] = new RemoteStore($service, $key, $endpoint);
                }
            } else {
                $this->stores[] = new RemoteStore($service, null, $this->config['endpoints']);
            }
        } else {
            $this->stores[] = new LocalStore($service, $this->config['local'] ?? null);
        }
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

    public function stores()
    {
        return $this->stores;
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
