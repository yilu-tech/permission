<?php


namespace YiluTech\Permission;

use Illuminate\Support\Facades\Redis;

class CacheDriver
{
    protected $config;

    /**
     * @var \Predis\Client
     */
    protected $driver;

    protected $prefix;

    protected $originalPrefix;

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->setPrefix($this->config['prefix'] ?? 'sharecache');
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix . ':';
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function getDriver()
    {
        if (!$this->driver) {
            $this->driver = Redis::connection();
            $options = $this->driver->getOptions();
            if ($options->prefix) {
                $this->originalPrefix = $options->prefix->getPrefix();
            }
        }
        return $this->driver;
    }

    public function __call($name, $arguments)
    {
        $driver = $this->getDriver();

        $options = $this->driver->getOptions();

        if ($options->prefix) {
            $options->prefix->setPrefix($this->prefix);
        }

        $result = $driver->{$name}(...$arguments);

        if ($options->prefix) {
            $options->prefix->setPrefix($this->originalPrefix);
        }

        return $result;
    }
}
